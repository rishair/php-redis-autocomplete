Redis Autocomplete
==================

Autocomplete implementation using PHP+Redis.

Inspired by https://github.com/seatgeek/soulmate

This library handles a basic implementation of autocomplete with sorted results (according to "scores") as well as arbitrary metadata for results. Also has the ability to separate different autocomplete databases in to "bins" (e.g. have separate bins "users" and another for "videos" so when querying against "users" it doesn't show results from "videos")

Getting started
---------------

Start off by copying the file into your project and initiating a valid instance of Predis (https://github.com/nrk/predis). 

Once the class is loaded, initiate a new instance of RedisAutocomplete

	$auto = new RedisAutocomplete($predis, "users");
	
We'll use "users" as the bin (a category to classify this set of autocompletes) in this example



### Storing data

To store data you must have a unique ID for an item and the phrase that should be searchable.  You can optionally add a score that would effect the order that the item shows up in when being fetched. You also have the option of passing in an arbitrary data object that is converted to JSON and stored with the unique ID.

	$auto->Store(2, "cat");
	$auto->Store(3, "care");
	$auto->Store(4, "caress");
	$auto->Store(5, "cars");
	$auto->Store(6, "camera");


### Retrieving data

Fetching data is as easy as it gets:
	
	$auto->Find("car");

Which returns

	[3, 4, 5]


Copyright (c) 2011 Rishi Ishairzay, released under the MIT license   