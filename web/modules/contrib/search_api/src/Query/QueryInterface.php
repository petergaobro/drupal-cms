<?php

namespace Drupal\search_api\Query;

use Drupal\search_api\IndexInterface;
use Drupal\search_api\ParseMode\ParseModeInterface;

/**
 * Represents a search query on a Search API index.
 *
 * Methods not returning something else will return the object itself, so calls
 * can be chained.
 */
interface QueryInterface extends ConditionSetInterface {

  /**
   * Constant representing ascending sorting.
   */
  const SORT_ASC = 'ASC';

  /**
   * Constant representing descending sorting.
   */
  const SORT_DESC = 'DESC';

  /**
   * Constant representing a completely unprocessed search.
   *
   * No processors or hooks will be invoked for searches with this processing
   * level.
   */
  const PROCESSING_NONE = 0;

  /**
   * Constant representing a search with only basic processing.
   *
   * Hook implementations or processors adding extra functionality, not
   * necessary for a basic search, should ignore searches with this level.
   *
   * Typical examples for such "extra functionality" would be facets,
   * spellchecking or highlighting.
   */
  const PROCESSING_BASIC = 1;

  /**
   * Constant representing a search with normal/full processing.
   *
   * This is the default for queries where no processing level has been
   * explicitly set.
   */
  const PROCESSING_FULL = 2;

  /**
   * Instantiates a new instance of this query class.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index for which the query should be created.
   * @param array $options
   *   (optional) The options to set for the query.
   *
   * @return static
   *   A query object to use.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if a search on that index (or with those options) won't be
   *   possible.
   */
  public static function create(IndexInterface $index, array $options = []);

  /**
   * Retrieves the search ID.
   *
   * @param bool $generate
   *   (optional) If TRUE and no search ID was set yet for this query, generate
   *   one automatically. If FALSE, NULL will be returned in this case.
   *
   * @return string|null
   *   The search ID, or NULL if none was set yet and $generate is FALSE.
   */
  public function getSearchId($generate = TRUE);

  /**
   * Sets the search ID.
   *
   * The search ID is a freely-chosen machine name identifying this search query
   * for purposes of identifying the query later in the page request. It will be
   * used, amongst other things, to identify the query in the search results
   * cache service.
   *
   * If the set ID is the same as a display plugin ID, this will also
   * automatically set that display plugin for this query. Queries for the same
   * display or search page should therefore usually use the same search ID.
   *
   * @param string $search_id
   *   The new search ID.
   *
   * @return $this
   *
   * @see \Drupal\search_api\Query\QueryInterface::getDisplayPlugin()
   * @see \Drupal\search_api\Query\ResultsCacheInterface
   */
  public function setSearchId($search_id);

  /**
   * Retrieves the search display associated with this query (if any).
   *
   * If the search ID set for this query corresponds to a display plugin ID,
   * that display will be returned. Otherwise, NULL is returned.
   *
   * @return \Drupal\search_api\Display\DisplayInterface|null
   *   The search display associated with this query, if any; NULL otherwise.
   */
  public function getDisplayPlugin();

  /**
   * Retrieves the parse mode.
   *
   * @return \Drupal\search_api\ParseMode\ParseModeInterface
   *   The parse mode.
   */
  public function getParseMode();

  /**
   * Sets the parse mode.
   *
   * @param \Drupal\search_api\ParseMode\ParseModeInterface $parse_mode
   *   The parse mode.
   *
   * @return $this
   */
  public function setParseMode(ParseModeInterface $parse_mode);

  /**
   * Retrieves the languages that will be searched by this query.
   *
   * @return string[]|null
   *   The language codes of languages that will be searched by this query, or
   *   NULL if there shouldn't be any restriction on the language.
   */
  public function getLanguages();

  /**
   * Sets the languages that should be searched by this query.
   *
   * @param string[]|null $languages
   *   The language codes to search for, or NULL to not restrict the query to
   *   specific languages.
   *
   * @return $this
   */
  public function setLanguages(?array $languages = NULL);

  /**
   * Creates a new condition group to use with this query object.
   *
   * @param string $conjunction
   *   The conjunction to use for the condition group – either 'AND' or 'OR'.
   * @param string[] $tags
   *   (optional) Tags to set on the condition group.
   *
   * @return \Drupal\search_api\Query\ConditionGroupInterface
   *   A condition group object that is set to use the specified conjunction.
   */
  public function createConditionGroup($conjunction = 'AND', array $tags = []);

  /**
   * Creates a new condition group and adds it to this query object.
   *
   * @param string $conjunction
   *   The conjunction to use for the condition group – either 'AND' or 'OR'.
   * @param string[] $tags
   *   (optional) Tags to set on the condition group.
   *
   * @return \Drupal\search_api\Query\ConditionGroupInterface
   *   The newly added condition group object.
   */
  public function createAndAddConditionGroup(string $conjunction = 'AND', array $tags = []): ConditionGroupInterface;

  /**
   * Sets the keys to search for.
   *
   * If this method is not called on the query before execution, this will be a
   * filters-only query.
   *
   * @param string|array|null $keys
   *   A string with the search keys, in one of the formats specified by
   *   getKeys(). A passed string will be parsed according to the set parse
   *   mode. Use NULL to not use any search keys.
   *
   * @return $this
   */
  public function keys($keys = NULL);

  /**
   * Sets the fields that will be searched for the search keys.
   *
   * If this is not called, all fulltext fields will be searched.
   *
   * @param array $fields
   *   An array containing fulltext fields that should be searched.
   *
   * @return $this
   */
  public function setFulltextFields(?array $fields = NULL);

  /**
   * Adds a sort directive to this search query.
   *
   * If no sort is manually set, the results will be sorted descending by
   * relevance.
   *
   * @param string $field
   *   The ID of the field to sort by. In addition to all indexed fields on the
   *   index, the following special field IDs may be used:
   *   - search_api_relevance: Sort by relevance.
   *   - search_api_datasource: Sort by datasource.
   *   - search_api_language: Sort by language.
   *   - search_api_id: Sort by item ID.
   * @param string $order
   *   The order to sort items in – one of the SORT_* constants.
   *
   * @return $this
   *
   * @see \Drupal\search_api\Query\QueryInterface::SORT_ASC
   * @see \Drupal\search_api\Query\QueryInterface::SORT_DESC
   */
  public function sort($field, $order = self::SORT_ASC);

  /**
   * Adds a range of results to return.
   *
   * This will be saved in the query's options. If called without parameters,
   * this will remove all range restrictions previously set.
   *
   * @param int|null $offset
   *   The zero-based offset of the first result returned.
   * @param int|null $limit
   *   The number of results to return.
   *
   * @return $this
   */
  public function range($offset = NULL, $limit = NULL);

  /**
   * Retrieves the processing level for this query.
   *
   * @return int
   *   The processing level of this query, as one of the
   *   \Drupal\search_api\Query\QueryInterface::PROCESSING_* constants.
   *
   * @see \Drupal\search_api\Query\QueryInterface::PROCESSING_NONE
   * @see \Drupal\search_api\Query\QueryInterface::PROCESSING_BASIC
   * @see \Drupal\search_api\Query\QueryInterface::PROCESSING_FULL
   */
  public function getProcessingLevel();

  /**
   * Sets the processing level for this query.
   *
   * @param int $level
   *   The processing level of this query, as one of the
   *   \Drupal\search_api\Query\QueryInterface::PROCESSING_* constants.
   *
   * @return $this
   */
  public function setProcessingLevel($level);

  /**
   * Aborts this query.
   *
   * This will mean that, while the query object otherwise acts normally, it
   * won't be passed to the server and won't return any results.
   *
   * @param \Drupal\Component\Render\MarkupInterface|string|null $error_message
   *   (optional) A translated error message explaining the reason why the
   *   query was aborted.
   *
   * @return $this
   */
  public function abort($error_message = NULL);

  /**
   * Determines whether this query was aborted.
   *
   * @return bool
   *   TRUE if the query was aborted, FALSE otherwise.
   */
  public function wasAborted();

  /**
   * Retrieves the error message explaining why this query was aborted, if any.
   *
   * @return \Drupal\Component\Render\MarkupInterface|string|null
   *   An error message, if set, or NULL if none was set. Be aware that a NULL
   *   message does not have to mean that the query was not aborted.
   */
  public function getAbortMessage();

  /**
   * Executes this search query.
   *
   * The results of the search will be cached on the query, so subsequent calls
   * of this method will always return the same result set (even if conditions
   * were changed in between). If you want to re-execute a query, use
   * getOriginalQuery().
   *
   * @return \Drupal\search_api\Query\ResultSetInterface
   *   The results of the search.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if an error occurred during the search.
   */
  public function execute();

  /**
   * Prepares the query object for the search.
   *
   * This method should always be called by execute() and contain all necessary
   * operations that have to be execute before the query is passed to the
   * server's search() method.
   */
  public function preExecute();

  /**
   * Postprocesses the search results before they are returned.
   *
   * This method should always be called by execute() and contain all necessary
   * operations after the results are returned from the server.
   */
  public function postExecute();

  /**
   * Determines whether this query has been executed already.
   *
   * @return bool
   *   TRUE if this query has been executed already, FALSE otherwise.
   */
  public function hasExecuted();

  /**
   * Retrieves this query's result set.
   *
   * If this query hasn't been executed yet, the results will be incomplete.
   *
   * @return \Drupal\search_api\Query\ResultSetInterface
   *   The results of the search.
   *
   * @see \Drupal\search_api\Query\QueryInterface::hasExecuted()
   */
  public function getResults();

  /**
   * Retrieves the index associated with this search.
   *
   * @return \Drupal\search_api\IndexInterface
   *   The search index this query should be executed on.
   */
  public function getIndex();

  /**
   * Retrieves the search keys for this query.
   *
   * @return array|string|null
   *   This object's search keys, in the format described by
   *   \Drupal\search_api\ParseMode\ParseModeInterface::parseInput(). Or NULL if
   *   the query doesn't have any search keys set.
   *
   * @see keys()
   */
  public function &getKeys();

  /**
   * Retrieves the unparsed search keys for this query as originally entered.
   *
   * @return array|string|null
   *   The unprocessed search keys, exactly as passed to this object. Has the
   *   same format as the return value of QueryInterface::getKeys().
   *
   * @see keys()
   */
  public function getOriginalKeys();

  /**
   * Retrieves the fulltext fields that will be searched for the search keys.
   *
   * @return string[]|null
   *   An array containing the fields that should be searched for the search
   *   keys, or NULL if all indexed fulltext fields should be used.
   *
   * @see setFulltextFields()
   */
  public function &getFulltextFields();

  /**
   * Retrieves the condition group object associated with this search query.
   *
   * @return \Drupal\search_api\Query\ConditionGroupInterface
   *   This object's associated condition group object.
   */
  public function getConditionGroup();

  /**
   * Retrieves the sorts set for this query.
   *
   * @return string[]
   *   An array specifying the sort order for this query. Array keys are the
   *   field IDs in order of importance, the values are the respective order in
   *   which to sort the results according to the field.
   *
   * @see sort()
   */
  public function &getSorts();

  /**
   * Retrieves an option set on this search query.
   *
   * @param string $name
   *   The name of the option.
   * @param mixed $default
   *   (optional) The value to return if the specified option is not set.
   *
   * @return mixed
   *   The value of the option with the specified name, if set. $default
   *   otherwise.
   */
  public function getOption($name, $default = NULL);

  /**
   * Sets an option for this search query.
   *
   * @param string $name
   *   The name of an option. The following options are recognized by default:
   *   - offset: The position of the first returned search results relative to
   *     the whole result in the index.
   *   - limit: The maximum number of search results to return. -1 means no
   *     limit.
   *   - 'skip result count': If present and set to TRUE, the search's result
   *     count will not be needed. Service classes can check for this option to
   *     possibly avoid executing expensive operations to compute the result
   *     count in cases where it is not needed.
   *   - search_api_access_account: The account which will be used for entity
   *     access checks, if available and enabled for the index.
   *   - search_api_bypass_access: If set to TRUE, entity access checks will be
   *     skipped, even if enabled for the index.
   *   - search_api_retrieved_field_values: A list of IDs of fields whose values
   *     should be returned along with the results by the backend, if possible.
   *     For backends that support retrieving fields values, this allows them to
   *     only retrieve the values that are actually needed.
   *   - search_api_included_languages: A list of all languages that should be
   *     included in the query, in case there were any restrictions. This is
   *     mostly helpful if getLanguages() returns NULL but there might still be
   *     just a subset of all available languages included.
   *   However, contrib modules might introduce arbitrary other keys with their
   *   own, special meaning. (Usually they should be prefixed with the module
   *   name, though, to avoid conflicts.)
   * @param mixed $value
   *   The new value of the option.
   *
   * @return mixed
   *   The option's previous value, or NULL if none was set.
   */
  public function setOption($name, $value);

  /**
   * Retrieves all options set for this search query.
   *
   * The return value is a reference to the options so they can also be altered
   * this way.
   *
   * @return array
   *   An associative array of query options.
   */
  public function &getOptions();

  /**
   * Sets the given tag on this query.
   *
   * Tags are strings that categorize a query. A query may have any number of
   * tags. Tags are used to mark a query so that alter hooks may decide if they
   * wish to take action. Tags should be all lower-case and contain only
   * letters, numbers, and underscore, and start with a letter. That is, they
   * should follow the same rules as PHP identifiers in general.
   *
   * The call will be ignored if the tag is already set on this query.
   *
   * @param string $tag
   *   The tag to set.
   *
   * @return $this
   *
   * @see hook_search_api_query_TAG_alter()
   */
  public function addTag($tag);

  /**
   * Checks whether a certain tag was set on this search query.
   *
   * @param string $tag
   *   The tag to check for.
   *
   * @return bool
   *   TRUE if the tag was set for this search query, FALSE otherwise.
   */
  public function hasTag($tag);

  /**
   * Determines whether this query has all the given tags set on it.
   *
   * @param string ...
   *   The tags to check for.
   *
   * @return bool
   *   TRUE if all the method parameters were set as tags on this query; FALSE
   *   otherwise.
   */
  public function hasAllTags();

  /**
   * Determines whether this query has any of the given tags set on it.
   *
   * @param string ...
   *   The tags to check for.
   *
   * @return bool
   *   TRUE if any of the method parameters was set as a tag on this query;
   *   FALSE otherwise.
   */
  public function hasAnyTag();

  /**
   * Retrieves the tags set on this query.
   *
   * See README.md for a list of all known query tags.
   *
   * @return string[]
   *   The tags associated with this search query, as both the array keys and
   *   values. Returned by reference so it's possible, for example, to remove
   *   existing tags.
   */
  public function &getTags();

  /**
   * Retrieves the original version of the query, before preprocessing occurred.
   *
   * Will be a clone of this query if preprocessing has not already run.
   *
   * @return \Drupal\search_api\Query\Query
   *   The original, unpreprocessed version of this query.
   */
  public function getOriginalQuery();

}
