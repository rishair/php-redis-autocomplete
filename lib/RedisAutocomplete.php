<?

class RedisAutocomplete {

	const MIN_LETTERS = 2;
	
	public static $EXCLUDE = array(
		'and' => 1,
		'or' => 1,
		'the' => 1,
	);
	
	private $redis;
	private $bin;
	
	public function __construct($redis, $bin) {
		$this->redis = $redis;
		
		if (func_num_args() > 1) 	$bin = func_get_args();
		if (is_array($bin)) 		$bin = implode(':', $bin);
		$this->bin = $this->Normalize($bin);
	}
	
	// Take a string and remove unalphabetic characters and make it lowercase
	private function Normalize($phrase) {
		return preg_replace('~[^a-z0-9 ]+~', '', strtolower($phrase));
	}
	
	// Take a string, normalize it then return an array of words to match against
	public function Words($phrase) {
		$phrase = explode(' ', $phrase);
		$filtered = array();
		
		foreach ($phrase as $word) {
			// Remove excluded words
			if (!isset(self::$EXCLUDE[$word]) && isset($word[self::MIN_LETTERS])) {
				array_push($filtered, $word);
			}
		}
		return $filtered;
	}
	
	public function WordPrefixes($word) {
		$array = array();
		if (is_array($word)) {
			// If an array of words is passed in then recursively call on each element
			foreach ($word as $w) {
				$array = array_merge($array, $this->WordPrefixes($w));
			}
			return $array;
		}
		
		// Start at the minimum amount of letters till the end of the word
		// e.g. "care" gives ["ca", "car", "care"]
		for ($i = self::MIN_LETTERS, $k = strlen($word); $i <= $k; $i++) {
			array_push($array, substr($word, 0, $i));
		}
		return $array;
	}
	
	private function PrefixKey($prefix) {
		return '_:' . $this->bin . ':' . $prefix;
	}
	
	private function MetaKey($suffix) {
		return '_:' . $this->bin . '>' . $suffix;
	}
	
	public function Store($id, $phrase, $score = 1, $data = false) {
		// Normalize string (strip non-alpha numeric, make lower case)
		$normalized = $this->Normalize($phrase);
	
		// Split phrase into normalized words
		$words = $this->Words($normalized);
	
		// Get prefixes for each word
		$prefixes = $this->WordPrefixes($words);
		
		foreach ($prefixes as $prefix) {
			// Add the prefix and its identifier to the set
			$this->redis->zadd($this->PrefixKey($prefix), $score, $id);
			
			// Store the phrase that is associated with the ID in a hash
			$this->redis->hset($this->MetaKey('ids'), $id, $normalized);
			
			// If data is passed in with it, then store the data as well
			if ($data)
				$this->redis->hset($this->MetaKey('data'), $id, json_encode($data));
		}
		
	}
	
	public function Find($phrase, $count = 10) {
		
		// Normalize the words
		$normalized = $this->Normalize($phrase);
		
		// Get a normalized array of all the words
		$words = $this->Words($normalized);
		
		// Sort them for caching purposes (e.g. both "man power" and "power man" will
		// point to the same cache
		sort($words);
		$joined = implode('_', $words);
		
		$key = $this->PrefixKey('cache:' . $joined);
		
		foreach ($words as &$w) {
			// Replace the words with their respective prefix keys
			$w = $this->PrefixKey($w);
		}
		
		// Check the cache to see if we stored the intersection already
		$range = $this->redis->zrevrange($key, 0, $count);
		
		if (!$range) {
			
			// Find the intersection of all the results and store it in a separate key
			call_user_func_array(array($this->redis, 'zinterstore'), array_merge(array(
				$key, count($words),
			), $words));
			
			$range = $this->redis->zrevrange($key, 0, $count);
		}
		
		
		$this->redis->expire($key, MINUTE * 10);
		
		return $range;
	}


}

?>