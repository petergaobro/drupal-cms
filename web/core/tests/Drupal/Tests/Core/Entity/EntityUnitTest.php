<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\Cache;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeRepositoryInterface;
use Drupal\Core\Language\Language;
use Drupal\entity_test\Entity\EntityTestMul;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;

/**
 * @coversDefaultClass \Drupal\Core\Entity\EntityBase
 * @group Entity
 * @group Access
 */
class EntityUnitTest extends UnitTestCase {

  /**
   * The entity under test.
   *
   * @var \Drupal\Core\Entity\EntityInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entity;

  /**
   * The entity type used for testing.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityType;

  /**
   * The entity type manager used for testing.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * The ID of the type of the entity under test.
   *
   * @var string
   */
  protected $entityTypeId;

  /**
   * The route provider used for testing.
   *
   * @var \Drupal\Core\Routing\RouteProvider|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $routeProvider;

  /**
   * The UUID generator used for testing.
   *
   * @var \Drupal\Component\Uuid\UuidInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $uuid;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $languageManager;

  /**
   * The mocked cache tags invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $cacheTagsInvalidator;

  /**
   * The entity values.
   *
   * @var array
   */
  protected $values;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->values = [
      'id' => 1,
      'langcode' => 'en',
      'uuid' => '3bb9ee60-bea5-4622-b89b-a63319d10b3a',
    ];
    $this->entityTypeId = $this->randomMachineName();

    $this->entityType = $this->createMock('\Drupal\Core\Entity\EntityTypeInterface');
    $this->entityType->expects($this->any())
      ->method('getListCacheTags')
      ->willReturn([$this->entityTypeId . '_list']);

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->entityTypeManager->expects($this->any())
      ->method('getDefinition')
      ->with($this->entityTypeId)
      ->willReturn($this->entityType);

    $this->uuid = $this->createMock('\Drupal\Component\Uuid\UuidInterface');

    $this->languageManager = $this->createMock('\Drupal\Core\Language\LanguageManagerInterface');
    $this->languageManager->expects($this->any())
      ->method('getLanguage')
      ->with('en')
      ->willReturn(new Language(['id' => 'en']));

    $this->cacheTagsInvalidator = $this->prophesize('Drupal\Core\Cache\CacheTagsInvalidator');

    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $this->entityTypeManager);
    $container->set('uuid', $this->uuid);
    $container->set('language_manager', $this->languageManager);
    $container->set('cache_tags.invalidator', $this->cacheTagsInvalidator->reveal());
    \Drupal::setContainer($container);

    $this->entity = new StubEntityBase($this->values, $this->entityTypeId);
  }

  /**
   * @covers ::id
   */
  public function testId(): void {
    $this->assertSame($this->values['id'], $this->entity->id());
  }

  /**
   * @covers ::uuid
   */
  public function testUuid(): void {
    $this->assertSame($this->values['uuid'], $this->entity->uuid());
  }

  /**
   * @covers ::isNew
   * @covers ::enforceIsNew
   */
  public function testIsNew(): void {
    // We provided an ID, so the entity is not new.
    $this->assertFalse($this->entity->isNew());
    // Force it to be new.
    $this->assertSame($this->entity, $this->entity->enforceIsNew());
    $this->assertTrue($this->entity->isNew());
  }

  /**
   * @covers ::getEntityType
   */
  public function testGetEntityType(): void {
    $this->assertSame($this->entityType, $this->entity->getEntityType());
  }

  /**
   * @covers ::bundle
   */
  public function testBundle(): void {
    $this->assertSame($this->entityTypeId, $this->entity->bundle());
  }

  /**
   * @covers ::label
   */
  public function testLabel(): void {
    $property_label = $this->randomMachineName();
    $this->entityType->expects($this->atLeastOnce())
      ->method('getKey')
      ->with('label')
      ->willReturn('label');

    // Set a dummy property on the entity under test to test that the label can
    // be returned form a property if there is no callback.
    $this->entityTypeManager->expects($this->atLeastOnce())
      ->method('getDefinition')
      ->with($this->entityTypeId)
      ->willReturn([
        'entity_keys' => [
          'label' => 'label',
        ],
      ]);
    $this->entity->label = $property_label;

    $this->assertSame($property_label, $this->entity->label());
  }

  /**
   * @covers ::access
   */
  public function testAccess(): void {
    $access = $this->createMock('\Drupal\Core\Entity\EntityAccessControlHandlerInterface');
    $operation = $this->randomMachineName();
    $access->expects($this->once())
      ->method('access')
      ->with($this->entity, $operation)
      ->willReturn(AccessResult::allowed());
    $access->expects($this->once())
      ->method('createAccess')
      ->willReturn(AccessResult::allowed());
    $this->entityTypeManager->expects($this->exactly(2))
      ->method('getAccessControlHandler')
      ->willReturn($access);

    $this->assertEquals(AccessResult::allowed(), $this->entity->access($operation));
    $this->assertEquals(AccessResult::allowed(), $this->entity->access('create'));
  }

  /**
   * @covers ::language
   */
  public function testLanguage(): void {
    $this->entityType->expects($this->any())
      ->method('getKey')
      ->willReturnMap([
        ['langcode', 'langcode'],
      ]);
    $this->assertSame('en', $this->entity->language()->getId());
  }

  /**
   * Setup for the tests of the ::load() method.
   */
  public function setupTestLoad(): void {
    // Base our mocked entity on a real entity class so we can test if calling
    // EntityBase::load() on the base class will bubble up to an actual entity.
    $this->entityTypeId = 'entity_test_mul';
    $methods = get_class_methods(EntityTestMul::class);
    unset($methods[array_search('load', $methods)]);
    unset($methods[array_search('loadMultiple', $methods)]);
    unset($methods[array_search('create', $methods)]);
    $this->entity = $this->getMockBuilder(EntityTestMul::class)
      ->disableOriginalConstructor()
      ->onlyMethods($methods)
      ->getMock();

  }

  /**
   * Tests EntityBase::load().
   *
   * When called statically on a subclass of Entity.
   *
   * @covers ::load
   */
  public function testLoad(): void {
    $this->setupTestLoad();

    $class_name = get_class($this->entity);

    $entity_type_repository = $this->createMock(EntityTypeRepositoryInterface::class);
    $entity_type_repository->expects($this->once())
      ->method('getEntityTypeFromClass')
      ->with($class_name)
      ->willReturn($this->entityTypeId);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->once())
      ->method('load')
      ->with(1)
      ->willReturn($this->entity);

    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with($this->entityTypeId)
      ->willReturn($storage);

    \Drupal::getContainer()->set('entity_type.repository', $entity_type_repository);

    // Call EntityBase::load statically and check that it returns the mock
    // entity.
    $this->assertSame($this->entity, $class_name::load(1));
  }

  /**
   * Tests EntityBase::loadMultiple().
   *
   * When called statically on a subclass of Entity.
   *
   * @covers ::loadMultiple
   */
  public function testLoadMultiple(): void {
    $this->setupTestLoad();

    $class_name = get_class($this->entity);

    $entity_type_repository = $this->createMock(EntityTypeRepositoryInterface::class);
    $entity_type_repository->expects($this->once())
      ->method('getEntityTypeFromClass')
      ->with($class_name)
      ->willReturn($this->entityTypeId);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->once())
      ->method('loadMultiple')
      ->with([1])
      ->willReturn([1 => $this->entity]);

    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with($this->entityTypeId)
      ->willReturn($storage);

    \Drupal::getContainer()->set('entity_type.repository', $entity_type_repository);

    // Call EntityBase::loadMultiple() statically and check that it returns the
    // mock entity.
    $this->assertSame([1 => $this->entity], $class_name::loadMultiple([1]));
  }

  /**
   * @covers ::create
   */
  public function testCreate(): void {
    $this->setupTestLoad();

    $class_name = get_class($this->entity);

    $entity_type_repository = $this->createMock(EntityTypeRepositoryInterface::class);
    $entity_type_repository->expects($this->once())
      ->method('getEntityTypeFromClass')
      ->with($class_name)
      ->willReturn($this->entityTypeId);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->once())
      ->method('create')
      ->with([])
      ->willReturn($this->entity);

    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with($this->entityTypeId)
      ->willReturn($storage);

    \Drupal::getContainer()->set('entity_type.repository', $entity_type_repository);

    // Call EntityBase::create() statically and check that it returns the mock
    // entity.
    $this->assertSame($this->entity, $class_name::create([]));
  }

  /**
   * @covers ::save
   */
  public function testSave(): void {
    $storage = $this->createMock('\Drupal\Core\Entity\EntityStorageInterface');
    $storage->expects($this->once())
      ->method('save')
      ->with($this->entity);

    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with($this->entityTypeId)
      ->willReturn($storage);

    $this->entity->save();
  }

  /**
   * @covers ::delete
   */
  public function testDelete(): void {
    $this->entity->id = $this->randomMachineName();
    $storage = $this->createMock('\Drupal\Core\Entity\EntityStorageInterface');
    // Testing the argument of the delete() method consumes too much memory.
    $storage->expects($this->once())
      ->method('delete');

    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with($this->entityTypeId)
      ->willReturn($storage);

    $this->entity->delete();
  }

  /**
   * @covers ::getEntityTypeId
   */
  public function testGetEntityTypeId(): void {
    $this->assertSame($this->entityTypeId, $this->entity->getEntityTypeId());
  }

  /**
   * @covers ::preSave
   */
  public function testPreSave(): void {
    // This method is internal, so check for errors on calling it only.
    $storage = $this->createMock('\Drupal\Core\Entity\EntityStorageInterface');
    // Our mocked entity->preSave() returns NULL, so assert that.
    $this->assertNull($this->entity->preSave($storage));
  }

  /**
   * @covers ::postSave
   */
  public function testPostSave(): void {
    // This method is internal, so check for errors on calling it only.
    $storage = $this->createMock('\Drupal\Core\Entity\EntityStorageInterface');

    // A creation should trigger the invalidation of the "list" cache tag.
    $this->entity->postSave($storage, FALSE);
    $this->cacheTagsInvalidator->invalidateTags([
      $this->entityTypeId . '_list',
    ])->shouldHaveBeenCalledOnce();

    // An update should trigger the invalidation of both the "list" and the
    // "own" cache tags.
    $this->entity->postSave($storage, TRUE);
    $this->cacheTagsInvalidator->invalidateTags([
      $this->entityTypeId . '_list',
      $this->entityTypeId . ':' . $this->values['id'],
    ])->shouldHaveBeenCalledOnce();
  }

  /**
   * @covers ::postSave
   */
  public function testPostSaveBundle(): void {
    $this->entityType->expects($this->atLeastOnce())
      ->method('hasKey')
      ->with('bundle')
      ->willReturn(TRUE);

    // This method is internal, so check for errors on calling it only.
    $storage = $this->createMock('\Drupal\Core\Entity\EntityStorageInterface');

    // A creation should trigger the invalidation of the global list cache tag
    // and the one for the bundle.
    $this->entity->postSave($storage, FALSE);
    $this->cacheTagsInvalidator->invalidateTags([
      $this->entityTypeId . '_list',
      $this->entityTypeId . '_list:' . $this->entity->bundle(),
    ])->shouldHaveBeenCalledOnce();

    // An update should trigger the invalidation of the "list", bundle list and
    // the "own" cache tags.
    $this->entity->postSave($storage, TRUE);
    $this->cacheTagsInvalidator->invalidateTags([
      $this->entityTypeId . '_list',
      $this->entityTypeId . '_list:' . $this->entity->bundle(),
      $this->entityTypeId . ':' . $this->values['id'],
    ])->shouldHaveBeenCalledOnce();
  }

  /**
   * @covers ::preCreate
   */
  public function testPreCreate(): void {
    // This method is internal, so check for errors on calling it only.
    $storage = $this->createMock('\Drupal\Core\Entity\EntityStorageInterface');
    $values = [];
    // Our mocked entity->preCreate() returns NULL, so assert that.
    $this->assertNull($this->entity->preCreate($storage, $values));
  }

  /**
   * @covers ::postCreate
   */
  public function testPostCreate(): void {
    // This method is internal, so check for errors on calling it only.
    $storage = $this->createMock('\Drupal\Core\Entity\EntityStorageInterface');
    // Our mocked entity->postCreate() returns NULL, so assert that.
    $this->assertNull($this->entity->postCreate($storage));
  }

  /**
   * @covers ::preDelete
   */
  public function testPreDelete(): void {
    // This method is internal, so check for errors on calling it only.
    $storage = $this->createMock('\Drupal\Core\Entity\EntityStorageInterface');
    // Our mocked entity->preDelete() returns NULL, so assert that.
    $this->assertNull($this->entity->preDelete($storage, [$this->entity]));
  }

  /**
   * @covers ::postDelete
   */
  public function testPostDelete(): void {
    $storage = $this->createMock('\Drupal\Core\Entity\EntityStorageInterface');
    $storage->expects($this->once())
      ->method('getEntityType')
      ->willReturn($this->entityType);

    $entities = [$this->values['id'] => $this->entity];
    $this->entity->postDelete($storage, $entities);

    $this->cacheTagsInvalidator->invalidateTags([
      $this->entityTypeId . '_list',
      $this->entityTypeId . ':' . $this->values['id'],
    ])->shouldHaveBeenCalledOnce();
  }

  /**
   * @covers ::postDelete
   */
  public function testPostDeleteBundle(): void {
    $this->entityType->expects($this->atLeastOnce())
      ->method('hasKey')
      ->with('bundle')
      ->willReturn(TRUE);
    $storage = $this->createMock('\Drupal\Core\Entity\EntityStorageInterface');
    $storage->expects($this->once())
      ->method('getEntityType')
      ->willReturn($this->entityType);

    $entities = [$this->values['id'] => $this->entity];
    $this->entity->postDelete($storage, $entities);

    // We avoid asserting on the order of array values, just that the values
    // all exist.
    $this->cacheTagsInvalidator->invalidateTags(Argument::allOf(
      Argument::containing($this->entityTypeId . '_list'),
      Argument::containing($this->entityTypeId . ':' . $this->values['id']),
      Argument::containing($this->entityTypeId . '_list:' . $this->entity->bundle()),
    ))->shouldHaveBeenCalledOnce();
  }

  /**
   * @covers ::postLoad
   */
  public function testPostLoad(): void {
    // This method is internal, so check for errors on calling it only.
    $storage = $this->createMock('\Drupal\Core\Entity\EntityStorageInterface');
    $entities = [$this->entity];
    // Our mocked entity->postLoad() returns NULL, so assert that.
    $this->assertNull($this->entity->postLoad($storage, $entities));
  }

  /**
   * @covers ::referencedEntities
   */
  public function testReferencedEntities(): void {
    $this->assertSame([], $this->entity->referencedEntities());
  }

  /**
   * @covers ::getCacheTags
   * @covers ::getCacheTagsToInvalidate
   * @covers ::addCacheTags
   */
  public function testCacheTags(): void {
    // Ensure that both methods return the same by default.
    $this->assertEqualsCanonicalizing([$this->entityTypeId . ':' . 1], $this->entity->getCacheTags());
    $this->assertEqualsCanonicalizing([$this->entityTypeId . ':' . 1], $this->entity->getCacheTagsToInvalidate());

    // Add an additional cache tag and make sure only getCacheTags() returns
    // that.
    $this->entity->addCacheTags(['additional_cache_tag']);

    // EntityTypeId is random so it can shift order. We need to duplicate the
    // sort from \Drupal\Core\Cache\Cache::mergeTags().
    $tags = [$this->entityTypeId . ':' . 1, 'additional_cache_tag'];
    $this->assertEqualsCanonicalizing($tags, $this->entity->getCacheTags());
    $this->assertEqualsCanonicalizing([$this->entityTypeId . ':' . 1], $this->entity->getCacheTagsToInvalidate());
  }

  /**
   * @covers ::getCacheContexts
   * @covers ::addCacheContexts
   */
  public function testCacheContexts(): void {
    $cache_contexts_manager = $this->getMockBuilder('Drupal\Core\Cache\Context\CacheContextsManager')
      ->disableOriginalConstructor()
      ->getMock();
    $cache_contexts_manager->method('assertValidTokens')->willReturn(TRUE);

    $container = new ContainerBuilder();
    $container->set('cache_contexts_manager', $cache_contexts_manager);
    \Drupal::setContainer($container);

    // There are no cache contexts by default.
    $this->assertEqualsCanonicalizing([], $this->entity->getCacheContexts());

    // Add an additional cache context.
    $this->entity->addCacheContexts(['user']);
    $this->assertEqualsCanonicalizing(['user'], $this->entity->getCacheContexts());
  }

  /**
   * @covers ::getCacheMaxAge
   * @covers ::mergeCacheMaxAge
   */
  public function testCacheMaxAge(): void {
    // Cache max age is permanent by default.
    $this->assertEquals(Cache::PERMANENT, $this->entity->getCacheMaxAge());

    // Set two cache max ages, the lower value is the one that needs to be
    // returned.
    $this->entity->mergeCacheMaxAge(600);
    $this->entity->mergeCacheMaxAge(1800);
    $this->assertEquals(600, $this->entity->getCacheMaxAge());
  }

}
