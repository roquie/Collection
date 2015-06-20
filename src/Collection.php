<?php
/**
 * Created by Roquie.
 * E-mail: roquie0@gmail.com
 * GitHub: Roquie
 *
 * Date: 19.06.15
 * Project: Collection.lc
 */

namespace Roquie;

use ArrayAccess;
use Closure;
use Countable;
use Iterator;
use JsonSerializable;
use Serializable;

class Collection implements ArrayAccess, JsonSerializable, Countable, Iterator, Serializable
{
    /**
     * The items contained in the collection.
     *
     * @var array
     */
    protected $items = [];

    /**
     * Create a new collection.
     *
     * @param  mixed $items
     */
    public function __construct($items = [])
    {
        $items = null === $items ? [] : $this->getArrayableItems($items);

        $this->items = (array) $items;
    }

    /**
     * Create a new collection instance if the value isn't one already.
     *
     * @param  mixed  $items
     * @return static
     */
    public static function make($items = null)
    {
        return new static($items);
    }

    /**
     * Get all of the items in the collection.
     *
     * @return array
     */
    public function all()
    {
        return $this->items;
    }

    /**
     * Collapse the collection of items into a single array.
     *
     * @return static
     */
    public function collapse()
    {
        $results = [];

        foreach ($this->items as $values)
        {
            if ($values instanceof Collection) $values = $values->all();

            $results = array_merge($results, $values);
        }

        return $results;
    }

    /**
     * Get an item from an array or object using "dot" notation.
     *
     * @param  mixed   $target
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    protected function dataGet($target, $key, $default = null)
    {
        if (null === $key) return $target;

        foreach (explode('.', $key) as $segment)
        {
            if (is_array($target))
            {
                if ( ! array_key_exists($segment, $target))
                {
                    return $this->value($default);
                }

                $target = $target[$segment];
            }
            elseif ($target instanceof ArrayAccess)
            {
                if ( ! isset($target[$segment]))
                {
                    return $this->value($default);
                }

                $target = $target[$segment];
            }
            elseif (is_object($target))
            {
                if ( ! isset($target->{$segment}))
                {
                    return $this->value($default);
                }

                $target = $target->{$segment};
            }
            else
            {
                return $this->value($default);
            }
        }

        return $target;
    }

    /**
     * Return the default value of the given value.
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function value($value)
    {
        return $value instanceof Closure ? $value() : $value;
    }

    /**
     * Determine if an item exists in the collection.
     *
     * @param  mixed  $key
     * @param  mixed  $value
     * @return bool
     */
    public function contains($key, $value = null)
    {
        if (func_num_args() == 2)
        {
            return $this->contains(function($k, $item) use ($key, $value)
            {
                return $this->dataGet($item, $key) == $value;
            });
        }

        if ($this->useAsCallable($key))
        {
            return ! is_null($this->first($key));
        }

        return in_array($key, $this->items);
    }

    /**
     * Diff the collection with the given items.
     *
     * @param $items
     *
     * @return static
     */
    public function diff($items)
    {
        return new static(array_diff($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Fetch a nested element of the collection.
     *
     * @param  string  $key
     * @return static
     */
    public function fetch($key)
    {
        $results = [];
        foreach (explode('.', $key) as $segment)
        {
            $results = [];

            foreach ($this->items as $value)
            {
                if (array_key_exists($segment, $value = (array) $value))
                {
                    $results[] = $value[$segment];
                }
            }

            $this->items = array_values($results);
        }

        return new static(array_values($results));
    }

    /**
     * Filter items by the given key value pair.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @param  bool  $strict
     * @return static
     */
    public function where($key, $value, $strict = true)
    {
        return $this->filter(function($item) use ($key, $value, $strict)
        {
            return $strict ? $this->dataGet($item, $key) === $value
                           : $this->dataGet($item, $key) == $value;
        });
    }

    /**
     * Filter items by the given key value pair using loose comparison.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return static
     */
    public function whereLoose($key, $value)
    {
        return $this->where($key, $value, false);
    }

    /**
     * Get the first item from the collection.
     *
     * @param  callable   $callback
     * @param  mixed      $default
     * @return mixed|null
     */
    public function first(callable $callback = null, $default = null)
    {
        if (is_null($callback))
        {
            return count($this->items) > 0 ? reset($this->items) : null;
        }

        foreach ($this->items as $key => $value)
        {
            if (call_user_func($callback, $key, $value)) return $value;
        }

        return $this->value($default);
    }

    /**
     * Get a flattened array of the items in the collection.
     *
     * @return static
     */
    public function flatten()
    {
        $return = [];

        array_walk_recursive($array, function($x) use (&$return) { $return[] = $x; });

        return new static($return);
    }

    /**
     * Flip the items in the collection.
     *
     * @return static
     */
    public function flip()
    {
        return new static(array_flip($this->items));
    }

    /**
     * Remove an item from the collection by key.
     *
     * @param  mixed  $keys
     * @return void
     */
    public function rm($keys)
    {
        $original =& $array;

        foreach ((array) $keys as $key)
        {
            $parts = explode('.', $key);

            while (count($parts) > 1)
            {
                $part = array_shift($parts);

                if (isset($array[$part]) && is_array($array[$part]))
                {
                    $array =& $array[$part];
                }
            }

            unset($array[array_shift($parts)]);

            // clean up after each pass
            $array =& $original;
        }
    }

    /**
     * Alias for ->rm()
     * @param $keys
     */
    public function forget($keys)
    {
        $this->rm($keys);
    }

    /**
     * Get an item from the collection by key.
     *
     * @param  mixed  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        if (empty($key)) return $this->items;

        if (isset($this->items[$key])) return $this->items[$key];

        foreach (explode('.', $key) as $segment)
        {
            if ( ! is_array($this->items) || ! array_key_exists($segment, $this->items))
            {
                return $this->value($default);
            }

            $this->items = $this->items[$segment];
        }

        return new static($this->items);
    }

    /**
     * Group an associative array by a field or using a callback.
     *
     * @param  callable|string  $groupBy
     * @return static
     */
    public function groupBy($groupBy)
    {
        if ( ! $this->useAsCallable($groupBy))
        {
            return $this->groupBy($this->valueRetriever($groupBy));
        }

        $results = [];

        foreach ($this->items as $key => $value)
        {
            $results[$groupBy($value, $key)][] = $value;
        }

        return new static($results);
    }

    /**
     * Key an associative array by a field or using a callback.
     *
     * @param  callable|string  $keyBy
     * @return static
     */
    public function keyBy($keyBy)
    {
        if ( ! $this->useAsCallable($keyBy))
        {
            return $this->keyBy($this->valueRetriever($keyBy));
        }

        $results = [];

        foreach ($this->items as $item)
        {
            $results[$keyBy($item)] = $item;
        }

        return new static($results);
    }

    /**
     * Determine if an item exists in the collection by key.
     *
     * @param  mixed  $key
     * @return bool
     */
    public function has($key)
    {
        $array = $this->items;
        if (empty($array) || null === $key) return false;

        if (array_key_exists($key, $array)) return true;

        foreach (explode('.', $key) as $segment)
        {
            if ( ! is_array($array) || ! array_key_exists($segment, $array))
            {
                return false;
            }

            $array = $array[$segment];
        }

        return true;
    }

    /**
     * Concatenate values of a given key as a string.
     *
     * @param  string  $value
     * @param  string  $glue
     * @return string
     */
    public function implode($value, $glue = null)
    {
        $first = $this->first();

        if (is_array($first) || is_object($first))
        {
            return implode($glue, $this->lists($value));
        }

        return implode($value, $this->items);
    }

    /**
     * Intersect the collection with the given items.
     *
     * @param $items
     * @return static
     */
    public function intersect($items)
    {
        return new static(array_intersect($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Determine if the collection is empty or not.
     *
     * @return bool
     */
    public function isEmpty()
    {
        return empty($this->items);
    }

    /**
     * Determine if the given value is callable, but not a string.
     *
     * @param  mixed  $value
     * @return bool
     */
    protected function useAsCallable($value)
    {
        return ! is_string($value) && is_callable($value);
    }

    /**
     * Get the keys of the collection items.
     *
     * @return static
     */
    public function keys()
    {
        return new static(array_keys($this->items));
    }

    /**
     * Get the last item from the collection.
     *
     * @return mixed|null
     */
    public function last()
    {
        return count($this->items) > 0 ? end($this->items) : null;
    }

    /**
     * Get an array with the values of a given key.
     *
     * @param  string  $value
     * @param  string  $key
     * @return array
     */
    public function lists($value, $key = null)
    {
        $results = [];

        foreach ($this->items as $item)
        {
            $itemValue = $this->dataGet($item, $value);

            // If the key is "null", we will just append the value to the array and keep
            // looping. Otherwise we will key the array using the value of the key we
            // received from the developer. Then we'll return the final array form.
            if (is_null($key))
            {
                $results[] = $itemValue;
            }
            else
            {
                $itemKey = $this->dataGet($item, $key);

                $results[$itemKey] = $itemValue;
            }
        }

        return new static($results);
    }

    /**
     * Run a map over each of the items.
     *
     * @param  callable  $callback
     * @return static
     */
    public function map(callable $callback)
    {
        return new static(array_map($callback, $this->items, array_keys($this->items)));
    }

    /**
     * Merge the collection with the given items.
     *
     * @param $items
     * @return static
     */
    public function merge($items)
    {
        return new static(array_merge($this->items, $this->getArrayableItems($items)));
    }

    /**
     * "Paginate" the collection by slicing it into a smaller collection.
     *
     * @param  int  $page
     * @param  int  $perPage
     * @return static
     */
    public function forPage($page, $perPage)
    {
        return $this->slice(($page - 1) * $perPage, $perPage);
    }

    /**
     * Get and remove the last item from the collection.
     *
     * @return mixed|null
     */
    public function pop()
    {
        return array_pop($this->items);
    }

    /**
     * Push an item onto the beginning of the collection.
     *
     * @param  mixed  $value
     * @return void
     */
    public function prepend($value)
    {
        array_unshift($this->items, $value);
    }

    /**
     * Push an item onto the end of the collection.
     *
     * @param  mixed  $value
     * @return void
     */
    public function push($value)
    {
        $this->offsetSet(null, $value);
    }

    /**
     * Pulls an item from the collection.
     *
     * @param  mixed  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function pull($key, $default = null)
    {
        $value = $this->get($key, $default);
        $this->rm($key);

        return new static($value);
    }

    /**
     * Put an item in the collection by key.
     *
     * @param  mixed  $key
     * @param  mixed  $value
     * @return void
     */
    public function put($key, $value)
    {
        $this->set($key, $value);
    }

    /**
     * @param $key
     * @param $value
     *
     * @return mixed
     */
    public function set($key, $value)
    {
        if (empty($key)) return new static($this->items = $value);

        $keys = explode('.', $key);

        while (count($keys) > 1)
        {
            $key = array_shift($keys);

            // If the key doesn't exist at this depth, we will just create an empty array
            // to hold the next value, allowing us to create the arrays to hold final
            // values at the correct depth. Then we'll keep digging into the array.
            if ( ! isset($this->items[$key]) || ! is_array($this->items[$key]))
            {
                $this->items[$key] = [];
            }

            $this->items =& $this->items[$key];
        }

        $this->items[array_shift($keys)] = $value;

        return new static($this->items);
    }

    /**
     * Get one or more items randomly from the collection.
     *
     * @param  int  $amount
     * @return mixed
     */
    public function random($amount = 1)
    {
        if ($this->isEmpty()) return;

        $keys = array_rand($this->items, $amount);

        $expression = is_array($keys) ? array_intersect_key($this->items, array_flip($keys)) : $this->items[$keys];

        return new static($expression);
    }

    /**
     * Reduce the collection to a single value.
     *
     * @param  callable  $callback
     * @param  mixed     $initial
     * @return mixed
     */
    public function reduce(callable $callback, $initial = null)
    {
        return new static(array_reduce($this->items, $callback, $initial));
    }

    /**
     * Create a collection of all elements that do not pass a given truth test.
     *
     * @param  callable|mixed  $callback
     * @return static
     */
    public function reject($callback)
    {
        if ($this->useAsCallable($callback))
        {
            return $this->filter(function($item) use ($callback)
            {
                return ! $callback($item);
            });
        }

        return $this->filter(function($item) use ($callback)
        {
            return $item != $callback;
        });
    }

    /**
     * Reverse items order.
     *
     * @return static
     */
    public function reverse()
    {
        return new static(array_reverse($this->items));
    }

    /**
     * Search the collection for a given value and return the corresponding key if successful.
     *
     * @param  mixed  $value
     * @param  bool   $strict
     * @return mixed
     */
    public function search($value, $strict = false)
    {
        if ( ! $this->useAsCallable($value))
        {
            return array_search($value, $this->items, $strict);
        }

        foreach ($this->items as $key => $item)
        {
            if ($value($item, $key)) return $key;
        }

        return false;
    }

    /**
     * Get and remove the first item from the collection.
     *
     * @return mixed|null
     */
    public function shift()
    {
        return new static(array_shift($this->items));
    }

    /**
     * Shuffle the items in the collection.
     *
     * @return $this
     */
    public function shuffle()
    {
        shuffle($this->items);

        return $this;
    }

    /**
     * Slice the underlying collection array.
     *
     * @param  int   $offset
     * @param  int   $length
     * @param  bool  $preserveKeys
     * @return static
     */
    public function slice($offset, $length = null, $preserveKeys = false)
    {
        return new static(array_slice($this->items, $offset, $length, $preserveKeys));
    }

    /**
     * Chunk the underlying collection array.
     *
     * @param  int   $size
     * @param  bool  $preserveKeys
     * @return static
     */
    public function chunk($size, $preserveKeys = false)
    {
        $chunks = [];

        foreach (array_chunk($this->items, $size, $preserveKeys) as $chunk)
        {
            $chunks[] = new static($chunk);
        }

        return new static($chunks);
    }

    /**
     * Sort through each item with a callback.
     *
     * @param  callable  $callback
     * @return $this
     */
    public function sort(callable $callback)
    {
        uasort($this->items, $callback);

        return $this;
    }

    /**
     * Sort the collection using the given callback.
     *
     * @param  callable|string  $callback
     * @param  int   $options
     * @param  bool  $descending
     * @return $this
     */
    public function sortBy($callback, $options = SORT_REGULAR, $descending = false)
    {
        $results = [];

        if ( ! $this->useAsCallable($callback))
        {
            $callback = $this->valueRetriever($callback);
        }

        // First we will loop through the items and get the comparator from a callback
        // function which we were given. Then, we will sort the returned values and
        // and grab the corresponding values for the sorted keys from this array.
        foreach ($this->items as $key => $value)
        {
            $results[$key] = $callback($value, $key);
        }

        $descending ? arsort($results, $options)
            : asort($results, $options);

        // Once we have sorted all of the keys in the array, we will loop through them
        // and grab the corresponding model so we can set the underlying items list
        // to the sorted version. Then we'll just return the collection instance.
        foreach (array_keys($results) as $key)
        {
            $results[$key] = $this->items[$key];
        }

        $this->items = $results;

        return $this;
    }

    /**
     * Sort the collection in descending order using the given callback.
     *
     * @param  callable|string  $callback
     * @param  int  $options
     * @return $this
     */
    public function sortByDesc($callback, $options = SORT_REGULAR)
    {
        return $this->sortBy($callback, $options, true);
    }

    /**
     * Splice portion of the underlying collection array.
     *
     * @param  int    $offset
     * @param  int    $length
     * @param  mixed  $replacement
     * @return static
     */
    public function splice($offset, $length = 0, $replacement = [])
    {
        return new static(array_splice($this->items, $offset, $length, $replacement));
    }

    /**
     * Get the sum of the given values.
     *
     * @param  callable|string|null  $callback
     * @return mixed
     */
    public function sum($callback = null)
    {
        if (is_null($callback))
        {
            return array_sum($this->items);
        }

        if ( ! $this->useAsCallable($callback))
        {
            $callback = $this->valueRetriever($callback);
        }

        return $this->reduce(function($result, $item) use ($callback)
        {
            return $result += $callback($item);
        }, 0);
    }

    /**
     * Take the first or last {$limit} items.
     *
     * @param  int  $limit
     * @return static
     */
    public function take($limit = null)
    {
        if ($limit < 0) return $this->slice($limit, abs($limit));

        return $this->slice(0, $limit);
    }

    /**
     * Transform each item in the collection using a callback.
     *
     * @param  callable  $callback
     * @return $this
     */
    public function transform(callable $callback)
    {
        $this->items = array_map($callback, $this->items);

        return $this;
    }

    /**
     * Return only unique items from the collection array.
     *
     * @return static
     */
    public function unique()
    {
        return new static(array_unique($this->items));
    }

    /**
     * Reset the keys on the underlying array.
     *
     * @return static
     */
    public function values()
    {
        return new static(array_values($this->items));
    }

    /**
     * Get a value retrieving callback.
     *
     * @param  string  $value
     * @return \Closure
     */
    protected function valueRetriever($value)
    {
        return function($item) use ($value)
        {
            return $this->dataGet($item, $value);
        };
    }

    /**
     * Get the collection of items as a plain array.
     *
     * @return array
     */
    public function toArray()
    {
        return array_map(function($value)
        {
            return $this->isArrayable($value) ? $value->toArray() : $value;

        }, $this->items);
    }

    public function isArrayable($instance)
    {
        return is_callable([$instance, 'toArray']);
    }

    /**
     * Count the number of items in the collection.
     *
     * @return int
     */
    public function count()
    {
        return count($this->items);
    }

    /**
     * Determine if an item exists at an offset.
     *
     * @param  mixed  $key
     * @return bool
     */
    public function offsetExists($key)
    {
        return $this->has($key);
    }

    /**
     * Get an item at a given offset.
     *
     * @param  mixed  $key
     * @return mixed
     */
    public function offsetGet($key)
    {
        return $this->get($key);
    }

    /**
     * Set the item at a given offset.
     *
     * @param  mixed  $key
     * @param  mixed  $value
     * @return void
     */
    public function offsetSet($key, $value)
    {
        $this->set($key, $value);
    }

    /**
     * Unset the item at a given offset.
     *
     * @param  string  $key
     * @return void
     */
    public function offsetUnset($key)
    {
        $this->rm($key);
    }

    /**
     * Convert the collection to its string representation.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->toJson();
    }

    /**
     * Results array of items from Collection or Arrayable.
     *
     * @param $items
     * @return array
     */
    protected function getArrayableItems($items)
    {
        if ($items instanceof Collection)
        {
            $items = $items->all();
        }
        elseif ($this->isArrayable($items))
        {
            $items = $items->toArray();
        }

        return $items;
    }

    /**
     * Execute a callback over each item.
     *
     * @param  callable  $callback
     * @return $this
     */
    public function each(callable $callback)
    {
        foreach($this as $item)
        {
            $callback($item);
        }

        return $this;
    }



    /**
     * Run a filter over each of the items.
     *
     * @param  callable $callback
     *
     * @return static
     */
    public function filter(callable $callback)
    {
        $return = [];
        foreach ($this as $k => $item)
        {
            if ($callback($item))
                $return[$k] = $item;
        }

        return new static($return);
    }

    /**
     * @param $key
     * @param $value
     *
     * @return bool
     */
    public function equals($key, $value)
    {
        return $this->get($key) === $value;
    }

    /**
     * @param $key
     * @param $value
     *
     * @return bool
     */
    public function equalsLoose($key, $value)
    {
        return $this->get($key) == $value;
    }

    /**
     * @param $key
     * @param $value
     *
     * @return bool
     */
    public function notEquals($key, $value)
    {
        return $this->get($key) !== $value;
    }

    /**
     * @param $key
     * @param $value
     *
     * @return bool
     */
    public function notEqualsLoose($key, $value)
    {
        return $this->get($key) != $value;
    }


    /**
     * For any type of array. Based in redshift code.
     * Based on http://php.net/manual/ru/function.array-filter.php#42298
     *
     * @param bool $toDelete
     * @param bool $caseSensitive
     *
     * @return mixed
     */
    public function clean($toDelete = false, $caseSensitive = false)
    {
        foreach ($this->items as $key => $value)
        {
            if (is_array($value))
            {
                $this->items[$key] = (new static($value))->clean($toDelete, $caseSensitive);
            }
            else
            {
                $func = $caseSensitive ? 'strstr' : 'stristr';
                if ($toDelete)
                {
                    if ($func($value, $toDelete) !== false)
                    {
                        $this->rm($this->items[$key]);
                    }
                }
                elseif (empty($value))
                {
                    $this->rm($this->items[$key]);
                }
            }
        }

        return $this;
    }


    /**
     * Returned an array if first argument is empty.
     *
     * @param $key
     *
     * @return Collection
     */
    public function getArray($key)
    {
        return $this->get($key, []);
    }

    /**
     * Returned an string if first argument is empty.
     *
     * @param $key
     *
     * @return Collection
     */
    public function getString($key)
    {
        return $this->get($key, '');
    }

    /**
     * Alias for getString($key);
     *
     * @param $key
     *
     * @return Collection
     */
    public function getStr($key)
    {
        return $this->getString($key);
    }

    /**
     * Returned an integer if first argument is empty.
     *
     * @param $key
     *
     * @return Collection
     */
    public function getInteger($key)
    {
        return $this->get($key, 0);
    }

    /**
     * Alias for getInteger($key);
     *
     * @param $key
     *
     * @return Collection
     */
    public function getInt($key)
    {
        return $this->getInteger($key);
    }

    /**
     * Returned an boolean if first argument is empty.
     *
     * @param $key
     *
     * @return Collection
     */
    public function getBoolean($key)
    {
        return $this->get($key, false);
    }

    /**
     * Alias for getBoolean($key);
     *
     * @param $key
     *
     * @return Collection
     */
    public function getBool($key)
    {
        return $this->getBoolean($key);
    }


    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the current element
     *
     * @link http://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     */
    public function current()
    {
        $current = (array) current($this->items);
        if ( ! $current)
            return null;
        else
            return new static($current);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Move forward to next element
     *
     * @link http://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     */
    public function next()
    {
        next($this->items);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the key of the current element
     *
     * @link http://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     */
    public function key()
    {
        return key($this->items);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Checks if current position is valid
     *
     * @link http://php.net/manual/en/iterator.valid.php
     * @return boolean The return value will be casted to boolean and then evaluated.
     *       Returns true on success or false on failure.
     */
    public function valid()
    {
        return key($this->items) !== null;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Rewind the Iterator to the first element
     *
     * @link http://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     */
    public function rewind()
    {
        reset($this->items);
    }


    /**
     * Get the collection of items as JSON.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->toArray(), $options | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Specify data which should be serialized to JSON
     */
    public  function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * String representation of object
     *
     * @link http://php.net/manual/en/serializable.serialize.php
     * @return string the string representation of the object or null
     */
    public function serialize()
    {
        return serialize($this->items);
    }

    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * Constructs the object
     *
     * @link http://php.net/manual/en/serializable.unserialize.php
     *
     * @param string $serialized <p>
     *                           The string representation of the object.
     *                           </p>
     *
     * @return void
     */
    public function unserialize($serialized)
    {
        $this->items = unserialize($serialized);
    }
}