<?php
/**
 * @author Yurko Fedoriv <yurko.fedoriv@gmail.com>
 */
namespace gearman;
/**
 * MemCache implements a cache application component based on {@link http://www.danga.com/memcached/ memcached}.
 *
 * It is tweaked component to implement more Memcached functionality.
 * NOTE: component does not support cache dependencies.
 *
 * MemCache can be configured with a list of memcache servers by settings
 * its {@link setServers servers} property. By default, MemCache assumes
 * there is a memcache server running on localhost at port 11211.
 *
 * See {@link CCache} manual for common cache operations that are supported by LMemCache.
 *
 * Note, there is no security measure to protected data in memcache.
 * All data in memcache can be accessed by any process running in the system.
 *
 * To use LMemCache as the cache application component, configure the application as follows,
 * <pre>
 * array(
 *     'components'=>array(
 *         'cache'=>array(
 *             'class'=>'\gearman\MemCache',
 *             'servers'=>array(
 *                 array(
 *                     'host'=>'server1',
 *                     'port'=>11211,
 *                     'weight'=>60,
 *                 ),
 *                 array(
 *                     'host'=>'server2',
 *                     'port'=>11211,
 *                     'weight'=>40,
 *                 ),
 *             ),
 *         ),
 *     ),
 * )
 * </pre>
 * In the above, two memcache servers are used: server1 and server2.
 * You can configure more properties of every server, including:
 * host, port, persistent, weight, timeout, retryInterval, status.
 * See {@link http://www.php.net/manual/en/function.memcache-addserver.php}
 * for more details.
 *
 */
use Memcached, Yii, CException;

class MemCache extends \CMemCache
{

    /**
     * @var array Options to be set after Memcached instance created.
     * @see MemCache::setOptions()
     */
    private $_options = array(
        Memcached::OPT_COMPRESSION => false,
    );

    /**
     * Initializes component.
     * Forces to use Memcached extension.
     * Applies options.
     *
     * @return void
     */
    public function init() {
        $this->useMemcached = true;
        parent::init();
        foreach ($this->_options as $option => &$value) {
            $this->getMemCache()->setOption($option, $value);
        }
    }

    /**
     * Setter
     *
     * @param array $value Array of options to be applied to Memcached instance
     *
     * @return bool whether it received valid options array.
     */
    public function setOptions($value) {
        if (!is_array($value)) {
            return false;
        }
        foreach ($value as $key => &$val) {
            $this->_options[$key] = $val;
        }
        return true;
    }

    /**
     * @param string $key a key identifying a value to be cached
     *
     * @return string a key generated from the provided key which ensures the uniqueness across applications
     */
    protected function generateUniqueKey($key) { return parent::generateUniqueKey($key); }

    /**
     * @return Memcached the memcached used by this component.
     */
    public function getMemCache() { return parent::getMemCache(); }


    /**
     * Retrieves a value from cache with a specified key.
     *
     * @param string $id             a key identifying the cached value
     *
     * @return mixed the value stored in cache, false if the value is not in the cache, expired or the dependency has changed.
     */
    public function get($id) {
        Yii::trace('Serving "' . $id . '" from cache', 'system.caching.' . get_class($this));
        return $this->getValue($this->generateUniqueKey($id));
    }

    /**
     * Retrieves multiple values from cache with the specified keys.
     *
     * @param array $ids             list of keys identifying the cached values
     *
     * @return array list of cached values corresponding to the specified keys. The array
     *        is returned in terms of (key,value) pairs.
     *        If a value is not cached or expired, the corresponding array value will be false.
     */
    public function mget($ids) {
        $uniqueIDs = array();
        $results = array();
        foreach ($ids as $id)
        {
            $uniqueIDs[$id] = $this->generateUniqueKey($id);
            $results[$id] = false;
        }
        $values = $this->getValues($uniqueIDs);
        foreach ($uniqueIDs as $id => $uniqueID)
        {
            if (!isset($values[$uniqueID])) {
                continue;
            }
            $results[$id] = $values[$uniqueID];
            Yii::trace('Serving "' . $id . '" from cache', 'system.caching.' . get_class($this));
        }
        return $results;
    }

    /**
     * Stores a value identified by a key into cache.
     * If the cache already contains such a key, the existing value and
     * expiration time will be replaced with the new ones.
     *
     * @param string $id             the key identifying the value to be cached
     * @param mixed $value           the value to be cached
     * @param integer $expire        the number of seconds in which the cached value will expire. 0 means never expire.
     * @param null $dependency       dummy param to fit interface
     *
     * @return boolean true if the value is successfully stored into cache, false otherwise
     */
    public function set($id, $value, $expire = 0, $dependency = null) {
        Yii::trace('Saving "' . $id . '" to cache', 'system.caching.' . get_class($this));
        return $this->setValue($this->generateUniqueKey($id), $value, $expire);
    }

    /**
     * Stores a value identified by a key into cache if the cache does not contain this key.
     * Nothing will be done if the cache already contains the key.
     *
     * @param string $id                   the key identifying the value to be cached
     * @param mixed $value                 the value to be cached
     * @param integer $expire              the number of seconds in which the cached value will expire. 0 means never expire.
     * @param null $dependency             dummy param to fit interface
     *
     * @return boolean true if the value is successfully stored into cache, false otherwise
     */
    public function add($id, $value, $expire = 0, $dependency = null) {
        Yii::trace('Adding "' . $id . '" to cache', 'system.caching.' . get_class($this));
        return $this->addValue($this->generateUniqueKey($id), $value, $expire);
    }


    /**
     * Store multiple items
     * Similar to MemCache::set(), but instead of a single key/value item, it works on multiple items specified in items. The expiration time applies to all the items at once.
     *
     * @param array $items           An array of key/value pairs to store on the server.
     * @param int $expire            The number of seconds in which the cached value will expire. 0 means never expire.
     *
     * @return bool Returns TRUE on success or FALSE on failure.
     */
    public function mset(array $items, $expire = 0) {
        if ($expire > 0) {
            $expire += time();
        }
        else
        {
            $expire = 0;
        }

        $itemsWithUID = array();
        foreach ($items as $key => &$value) {
            Yii::trace('Saving "' . $key . '" to cache', 'system.caching.' . get_class($this));
            $itemsWithUID[$this->generateUniqueKey($key)] = $value;
        }

        return $this->getMemCache()->setMulti($itemsWithUID, $expire);
    }

    /**
     * Replace the item under an existing key.
     * Similar to MemCache::set(), but the operation fails if the key does not exist on the server.
     *
     * @param string $id             The key under which to store the value.
     * @param mixed $value           The value to store.
     * @param int $expire            the number of seconds in which the cached value will expire. 0 means never expire.
     *
     * @return bool Returns TRUE on success or FALSE on failure. The MemCache::getResultCode() will return Memcached::RES_NOTSTORED if the key does not exist.
     */
    public function replace($id, $value, $expire = 0) {
        if ($expire > 0) {
            $expire += time();
        }
        else
        {
            $expire = 0;
        }
        Yii::trace('Replacing "' . $id . '" in cache', 'system.caching.' . get_class($this));
        return $this->getMemCache()->replace($this->generateUniqueKey($id), $value, $expire);
    }

    /**
     * Increments a numeric item's value by the specified offset. If the item's value is not numeric, it is treated as if the value were 0.
     * Memcached::increment() will fail if the item does not exist.
     *
     * @param string $id             The key of the item to increment.
     * @param int $offset            The amount by which to increment the item's value.
     *
     * @return int|bool New item's value on success or FALSE on failure. The MemCache::getResultCode() will return Memcached::RES_NOTFOUND if the key does not exist.
     */
    public function increment($id, $offset = 1) {
        Yii::trace('Incrementing "' . $id . '" in cache by ' . $offset, 'system.caching.' . get_class($this));
        return $this->getMemCache()->increment($this->generateUniqueKey($id), $offset);
    }

    /**
     * Decrements a numeric item's value by the specified offset. If the item's value is not numeric, it is treated as if the value were 0.
     * If the operation would decrease the value below 0, the new value will be 0. Memcached::decrement() will fail if the item does not exist.
     *
     * @param string $id                   The key of the item to increment.
     * @param int $offset                  The amount by which to decrement the item's value.
     *
     * @return int|bool New item's value on success or FALSE on failure. The LMemCache::getResultCode() will return Memcached::RES_NOTFOUND if the key does not exist.
     */
    public function decrement($id, $offset = 1) {
        Yii::trace('Decrementing "' . $id . '" in cache by ' . $offset, 'system.caching.' . get_class($this));
        return $this->getMemCache()->decrement($this->generateUniqueKey($id), $offset);
    }

    /**
     * Prepends the given value string to the value of an existing item.
     * The reason that value is forced to be a string is that prepending mixed types is not well-defined.
     *
     * @throws CException
     *
     * @param string $id             The key of the item to prepend the data to.
     * @param string $data           The string to prepend.
     *
     * @return bool Returns TRUE on success or FALSE on failure. The MemCache::getResultCode() will return Memcached::RES_NOTSTORED if the key does not exist.
     */
    public function prepend($id, $data) {
        if ($this->getMemCache()->getOption(Memcached::OPT_COMPRESSION)) {
            throw new CException(__METHOD__ . 'requires Memcached::OPT_COMPRESSION to be set to false');
        }
        Yii::trace('Prepending to "' . $id . '" in cache', 'system.caching.' . get_class($this));
        return $this->getMemCache()->prepend($this->generateUniqueKey($id), $data);
    }

    /**
     * Appends data to existing key in cache.
     * The reason that value is forced to be a string is that prepending mixed types is not well-defined.
     *
     * @param string $id   The key of the item to append the data to.
     * @param string $data The string to append.
     *
     * @return bool Returns TRUE on success or FALSE on failure. The MemCache::getResultCode() will return Memcached::RES_NOTSTORED if the key does not exist.
     */
    public function append($id, $data) {
        if ($this->getMemCache()->getOption(Memcached::OPT_COMPRESSION)) {
            throw new CException(__METHOD__ . 'requires Memcached::OPT_COMPRESSION to be set to false');
        }
        Yii::trace('Appending to "' . $id . '" in cache', 'system.caching.' . get_class($this));
        return $this->getMemCache()->append($this->generateUniqueKey($id), $data);
    }

    /**
     * Return the result code of the last operation
     *
     * @return int Result code of the last Memcached operation.
     */
    public function getResultCode() {
        return $this->getMemCache()->getResultCode();
    }

    /**
     * Return the message describing the result of the last operation
     *
     * @return string Message describing the result of the last Memcached operation.
     */
    public function getResultMessage() {
        return $this->getMemCache()->getResultMessage();
    }

    /**
     * Returns an array containing the state of all available memcache servers
     *
     * @link http://code.sixapart.com/svn/memcached/trunk/server/doc/protocol.txt
     * @return array Array of server statistics, one entry per server.
     */
    public function getStats() {
        return $this->getMemCache()->getStats();
    }
}
