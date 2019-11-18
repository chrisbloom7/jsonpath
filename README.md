# jsonpath scripts
Tidied versions of [Stefan Goessner's](https://github.com/goessner) [jsonPath scripts](http://goessner.net/articles/JsonPath/).

To try to better understand @goessner's JSONPath spec implementation I have been cleaning up the old versions of the files from his [old Google Code repository](https://code.google.com/archive/p/jsonpath/). No functionality has been added or removed, I've simply cleaned up the formatting, updated the variable names to be more meaningful, and added some debugging output.

Full credit for this code goes to Stefan Goessner for this library and it's functionality. If you decided to fork this repository please leave this attribution in place.

Note that while Goessner's work was the first implementation of a lexical JSONPath parser it's not the only one available, and each one introduces slight differences as there is no officially adopted spec. Other implementations include:

- https://github.com/FlowCommunications/JSONPath
- https://github.com/dchester/jsonpath
- https://github.com/joshbuddy/jsonpath
- https://github.com/Peekmo/JsonPath (also based on Goessner's original code)
- https://github.com/jmespath/jmespath.php
- as well as my own https://github.com/chrisbloom7/enumpath which is an implementation of JSONPath for Ruby objects
