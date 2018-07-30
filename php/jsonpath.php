<?php
/* JSONPath 0.8.0 - XPath for JSON
 *
 * Copyright (c) 2007 Stefan Goessner (goessner.net)
 * Licensed under the MIT (MIT-LICENSE.txt) licence.
 */

// API function
function jsonPath($json, $pathExpr, $args=null) {
  $jsonpath = new JsonPath();
  $jsonpath->resultType = ($args ? $args['resultType'] : "VALUE");
  $jsonpath->debug = ($args ? $args['debug'] : false);
  $normalizedPathExpr = $jsonpath->normalize($pathExpr);
  $jsonpath->json = $json;
  if ($pathExpr && $json && ($jsonpath->resultType == "VALUE" || $jsonpath->resultType == "PATH")) {
    $jsonpath->trace(preg_replace("/^\\$;/", "", $normalizedPathExpr), $json, "$");
    if (count($jsonpath->result)) {
      return $jsonpath->result;
    } else {
      return false;
    }
  }
}

// JsonPath class (internal use only)
class JsonPath {
   var $debug = false;
   var $json = null;
   var $resultType = "VALUE";
   var $result = array();

   // normalize path expression
  function normalize($pathExpr) {
    $this->d('normalize - called with arguments', $pathExpr);

    $pathExpr = preg_replace_callback("/[\['](\??\(.*?\))[\]']/",
                                      array(&$this, "_callback_01"),
                                      $pathExpr);
                                      $this->d('normalize', $pathExpr);

    // Matches "['" with ";", ";;;" or ";;" with ";..;", or "']" with ""
    $pathExpr = preg_replace(array("/'?\.'?|\['?/", "/;;;|;;/", "/;$|'?\]|'$/"),
                             array(";", ";..;", ""),
                             $pathExpr);
                             $this->d('normalize', $pathExpr);

    $pathExpr = preg_replace_callback("/#([0-9]+)/",
                                      array(&$this, "_callback_02"),
                                      $pathExpr);
                                      $this->d('normalize', $pathExpr);

    $this->result = array();  // result array was temporarily used as a buffer
    return $pathExpr;
  }

  // Converts filter (script) expression statements
  function _callback_01($matches) {
    $this->d('_callback_01 - called with arguments', $matches);
    $result = array_push($this->result, $matches[1]);
    $return = "[#".($result-1)."]";
    $this->d("_callback_01 - returning", $return);
    return $return;
  }

  // Unconverts filter (script) expression statements?
  function _callback_02($matches) {
    $this->d('_callback_02 - called with arguments', $matches);
    $return = $this->result[$matches[1]];
    $this->d("_callback_02 - returning", $return);
    return $return;
  }

  function asPath($pathExpr) {
    $this->d('asPath - called with arguments', $pathExpr);
    $pathSegments = explode(";", $pathExpr);
    $path = "$";
    for ($i = 1, $n = count($pathSegments); $i < $n; $i++) {
      $this->d('asPath - path segment #'.($i + 1), $pathSegments[$i]);
      $match = preg_match("/^[0-9*]+$/", $pathSegments[$i]);
      $this->d('asPath - path segment #'.($i + 1).' match', $match);
      $newSegment = $match ? ("[".$pathSegments[$i]."]") : ("['".$pathSegments[$i]."']");
      $this->d('asPath - path segment #'.($i + 1).' new segment', $newSegment);
      $path .= $newSegment;
    }
    $this->d('asPath - returning', $path);
    return $path;
  }

  // Adds a result to the results array
  function store($pathExpr, $json) {
    $this->d('store - called with arguments', $pathExpr, substr(serialize($json), 0, 25));
    if ($pathExpr) {
      $to_push = $this->resultType == "PATH" ? $this->asPath($pathExpr) : $json;
      $this->d('store - pushing', $to_push);
      array_push($this->result, $to_push);
    }
    $this->d('store - returning (pathExpr presence)', !!$pathExpr);
    return !!$pathExpr;
  }

  function trace($pathExpr, $json, $currentPath) {
    $this->d('trace - called with arguments', $pathExpr, substr(serialize($json), 0, 25), $currentPath);
    $this->d('trace - pathExpr presence', !!$pathExpr);
    if ($pathExpr) {
      $pathSegments = explode(";", $pathExpr);
      $currentSegment = array_shift($pathSegments);
      $remainingPathExpr = implode(";", $pathSegments);
      $this->d('trace', $pathSegments, $currentSegment, $remainingPathExpr);

      if (is_array($json) && array_key_exists($currentSegment, $json)) {
        $this->d('trace', 'array_key_exists');
        $this->trace($remainingPathExpr, $json[$currentSegment], $currentPath.";".$currentSegment);
      }
      else if ($currentSegment == "*") {
        $this->d('trace', '*');
        $this->walk($currentSegment, $remainingPathExpr, $json, $currentPath, array(&$this, "_callback_03"));
      }
      else if ($currentSegment === "..") {
        $this->d('trace', '..');
        $this->trace($remainingPathExpr, $json, $currentPath);
        $this->walk($currentSegment, $remainingPathExpr, $json, $currentPath, array(&$this, "_callback_04"));
      }
      else if (preg_match("/,/", $currentSegment)) { // [name1,name2,...]
        $this->d('trace', "[name1,name2,...]");
        for ($parts = preg_split("/'?,'?/", $currentSegment), $i = 0, $n = count($parts); $i < $n; $i++) {
          $this->d('trace', $i, $parts[$i]);
          $this->trace($parts[$i] . ";" . $remainingPathExpr, $json, $currentPath);
        }
      }
      else if (preg_match("/^\(.*?\)$/", $currentSegment)) { // [(expr)]
        $evaled = $this->evalx($currentSegment, $json);
        $this->d('trace', "[(expr)]", $evaled);
        $this->trace($evaled . ";" . $remainingPathExpr, $json, $currentPath);
      }
      else if (preg_match("/^\?\(.*?\)$/", $currentSegment)) { // [?(expr)]
        $this->d('trace', "[?(expr)]");
        $this->walk($currentSegment, $remainingPathExpr, $json, $currentPath, array(&$this, "_callback_05"));
      }
      else if (preg_match("/^(-?[0-9]*):(-?[0-9]*):?(-?[0-9]*)$/", $currentSegment)) { // [start:end:step] python slice syntax
        $this->d('trace', "[start:end:step]");
        $this->slice($currentSegment, $remainingPathExpr, $json, $currentPath);
      }
    }
    else {
      $this->d('trace - delegating to store', null);
      $this->store($currentPath, $json);
    }
  }

  function _callback_03($key, $_segment, $pathSegments, $json, $currentPath) {
    $this->d('_callback_03 - called with arguments', $key, $_segment, $pathSegments, substr(serialize($json), 0, 25), $currentPath);
    $this->trace($key . ";" . $pathSegments, $json, $currentPath);
  }

  function _callback_04($key, $_segment, $pathSegments, $json, $currentPath) {
    $this->d('_callback_04 - called with arguments', $key, $_segment, $pathSegments, substr(serialize($json), 0, 25), $currentPath);
    $this->d('_callback_04 - is_array($json[$key])', is_array($json[$key]));
    if (is_array($json[$key])) {
      $this->trace("..;" . $pathSegments, $json[$key], $currentPath . ";" . $key);
    }
  }

  function _callback_05($key, $segment, $pathSegments, $json, $currentPath) {
    $this->d('_callback_05 - called with arguments', $key, $segment, $pathSegments, substr(serialize($json), 0, 25), $currentPath);
    $evaled = $this->evalx(preg_replace("/^\?\((.*?)\)$/", "$1", $segment), $json[$key]);
    $this->d('_callback_05 - evaled', $evaled);
    if ($evaled) {
      $this->trace($key . ";" . $pathSegments, $json, $currentPath);
    }
  }

  /*
  walk - called with arguments
  ----------
  string(2) ".."
  string(7) "bicycle"
  string(25) "a:2:{s:5:"color";s:3:"red"
  string(15) "$;store;bicycle"
  array(2) {
    [0]=>
    object(JsonPath)#2 (3) {
      ["json"]=>
      array(1) { ... }
    [1]=>
    string(12) "_callback_04"
  }
  */
  function walk($segment, $pathSegments, $json, $currentPath, $callback) {
    $this->d('walk - called with arguments', $segment, $pathSegments, substr(serialize($json), 0, 25), $currentPath, $callback[1]);

    foreach($json as $key => $_val) {
      $this->d('walk - current key', $key);
      call_user_func($callback, $key, $segment, $pathSegments, $json, $currentPath);
    }
  }

  function slice($segment, $pathSegments, $json, $currentPath) {
    $this->d('slice - called with arguments', $segment, $pathSegments, substr(serialize($json), 0, 25), $currentPath);

    $this->d('slice - name?!', $name);

    $parts = explode(":", preg_replace("/^(-?[0-9]*):(-?[0-9]*):?(-?[0-9]*)$/", "$1:$2:$3", $name));
    $this->d('slice - parts', $parts);
    $len   = count($json);
    $start = (int) $parts[0] ? $parts[0] : 0;
    $end   = (int) $parts[1] ? $parts[1] : $len;
    $step  = (int) $parts[2] ? $parts[2] : 1;
    $this->d('slice - intermediate vars', $len, $start, $end, $step);

    $start = ($start < 0) ? max(0, $start + $len) : min($len, $start);
    $end   = ($end < 0)   ? max(0, $end + $len)   : min($len, $end);
    $this->d('slice - new start, end', $start, $end);

    for ($i = $start; $i < $end; $i += $step) {
      $this->d('slice', $i);
      $this->trace($i . ";" . $pathSegments, $json, $currentPath);
    }
  }

  function evalx($segment, $json) {
    $this->d('slice - called with arguments', $segment, substr(serialize($json), 0, 25));

    $name = "";
    $expr = preg_replace(array("/\\$/", "/@/"), array("\$this->json", "\$json"), $segment);
    $this->d('slice - expr', $expr);
    $result = eval("\$name = $expr;");

    $this->d('slice - result', $result);
    if ($result === FALSE) {
      print("(jsonPath) SyntaxError: " . $expr);
    }
    else {
      $this->d('slice - returning', $name);
      return $name;
    }
  }

  function d() {
    if ($this->debug) {
      print_r(func_get_arg(0)."\n");
      print_r("----------\n");
      for ($i = 1; $i < func_num_args(); $i++) {
        var_dump(func_get_arg($i));
      }
      print_r("\n");
    }
  }
}
?>
