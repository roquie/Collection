# Collection

DEPRECATED. Please, use original laravel collection. This package no longer maintained.

Simple PHP Collection based on Laravel 5 Collection.

## Install

`composer require roquie/collection`

## Usage (in progress ...)

Five minutes example: <br>
```php
$array = [
    'foo' => [
        'one',
        'two',
        'three',
        'bar' => [
            1, 2, 3
        ],
        'clean' => '',
        'test1' => 'val2qq',
        'test2' => 'val2ww'
    ]
];

$collection = Collection::make($array);
//1
foreach($collection as $collect) {
    echo $collect['undefined_index']; // null
}

//2 

$result = $collection->each(function($collect) {
    echo $collect['undefined_index']; // null
});

//3

$result = $collection->filter(function($collect) {
    return $collect->has('key'); 
});


//4. get items using "dot" notation
$collection->get('foo.0', <default>);
$collection->getArray('foo.0'); // return [] if empty
$collection->getInteger('foo.0'); // return 0 if empty
$collection->getBoolean('foo.0'); // return false if empty
$collection->getString('foo.0'); // return '' if empty


//5. remove item using "dot" notation
$collection->rm('foo.bar'); // delete an item


//6. has item using "dot" notation
$collection->rm('foo.bar'); // true (if not delete :) )
$collection->forgot('foo.bar'); //alias

//7. clean array

$collection->clean(); // key foo.clean deleted.
$collection->clean('val'); // keys foo.test1 and 2 will be removed

//8. set an item using "dot" notation

$collection->set('baz', ['key' => 'is awesome']);
$collection->put($key, $value); //alias


```

## License 
MIT
