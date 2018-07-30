# JSONPath 0.8.0 for PHP

## Example PHP usage

```php
include 'jsonpath.php';

$json = '{ "store": { "book": [  { "category": "reference", "author": "Nigel Rees", "title": "Sayings of the Century", "price": 8.95 }, { "category": "fiction", "author": "Evelyn Waugh", "title": "Sword of Honour", "price": 12.99 }, { "category": "fiction", "author": "Herman Melville", "title": "Moby Dick", "isbn": "0-553-21311-3", "price": 8.99 }, { "category": "fiction", "author": "J. R. R. Tolkien", "title": "The Lord of the Rings", "isbn": "0-395-19395-8", "price": 22.99 } ], "bicycle": { "color": "red", "price": 19.95 } } }';

$o = json_decode($json, true);

$data = jsonPath($o, "$..author");
$path = jsonPath($o, "$..author", array("resultType" => "PATH"));

print_r($data);

/*
Array
(
    [0] => Nigel Rees
    [1] => Evelyn Waugh
    [2] => Herman Melville
    [3] => J. R. R. Tolkien
)
*/

print_r($path);

/*
Array
(
    [0] => $['store']['book'][0]['author']
    [1] => $['store']['book'][1]['author']
    [2] => $['store']['book'][2]['author']
    [3] => $['store']['book'][3]['author']
)
*/
```
