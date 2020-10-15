<?php

namespace DQ;

use DOMNode;
use DOMNodeList;
use DQ\Traits\Encoding;

/**
 * Class DomQuery
 * @package DQ
 *
 * @property-read string $text
 * @property-read string $plaintext
 * @property-read string $innerHTML
 * @property-read string $outerHTML
 *
 */
class DomQuery extends DomQueryNodes
{
    /**
     * Node data
     *
     * @var array
     */
    private static $node_data = array();

    /**
     * Get the combined text contents of each element in the set of matched elements, including their descendants,
     * or set the text contents of the matched elements.
     *
     * @param  string|null  $val
     *
     * @return $this|string|null
     */
    public function text($val = null)
    {
        if ($val !== null) { // set node value for all nodes
            foreach ($this->nodes as $node) {
                $node->nodeValue = $val;
            }

            return $this;
        }
        if ($node = $this->getFirstElmNode()) { // get value for first node
            return $node->nodeValue;
        }

        return null;
    }

    /**
     * Get the HTML contents of the first element in the set of matched elements
     *
     * @param  string|null  $html_string
     * @return $this|string
     */
    public function html($html_string = null)
    {
        if ($html_string !== null) {
            // set html for all nodes
            foreach ($this as $node) {
                /* @var DomQuery $node */
                $node->get(0)->nodeValue = '';
                $node->append($html_string);
            }

            return $this;
        }
        // get html for first node
        return $this->getInnerHtml();
    }

    /**
     * Get the value of an attribute for the first element in the set of matched elements
     * or set one or more attributes for every matched element.
     *
     * @param  string  $name
     * @param  string  $val
     *
     * @return $this|string|string[]|null
     */
    public function attr(string $name, $val = null)
    {
        if ($val !== null) { // set attribute for all nodes
            foreach ($this->getElements() as $node) {
                $node->setAttribute($name, $val);
            }
            return $this;
        }
        if ($node = $this->getFirstElmNode()) { // get attribute value for first element

            if ('*' === $name) {
                $attrs = [];
                /* @var DOMNode $node */
                foreach ($node->attributes as $attr) {
                    $attrs[] = [$attr->nodeName => $attr->nodeValue];
                }
                return $attrs;
            }

            return $node->getAttribute($name);
        }

        return null;
    }

    /**
     * @return \Tightenco\Collect\Support\Collection
     */
    public function getAttributes()
    {
        $attrs = [];

        if ($node = $this->getFirstElmNode()) {
            if ($node instanceof \DOMElement) {
                /* @var DOMNode $attr */
                foreach ($node->attributes as $attr) {
                    $attrs[$attr->nodeName] = $attr->nodeValue;
                }
            }
        }

        return collect($attrs);
    }

    /**
     * Store arbitrary data associated with the matched elements or return the value at
     * the named data store for the first element in the set of matched elements.
     *
     * @param  string  $key
     * @param $val
     *
     * @return $this|string|object
     */
    public function data(string $key = null, $val = null)
    {
        $doc_hash = spl_object_hash($this->document);

        if ($val !== null) { // set data for all nodes
            if ( ! isset(self::$node_data[$doc_hash])) {
                self::$node_data[$doc_hash] = array();
            }
            foreach ($this->getElements() as $node) {
                if ( ! isset(self::$node_data[$doc_hash][self::getElementId($node)])) {
                    self::$node_data[$doc_hash][self::getElementId($node)] = (object) array();
                }
                self::$node_data[$doc_hash][self::getElementId($node)]->$key = $val;
            }
            return $this;
        }

        if ($node = $this->getFirstElmNode()) { // get data for first element
            if (isset(self::$node_data[$doc_hash]) && isset(self::$node_data[$doc_hash][self::getElementId($node)])) {
                if ($key === null) {
                    return self::$node_data[$doc_hash][self::getElementId($node)];
                } elseif (isset(self::$node_data[$doc_hash][self::getElementId($node)]->$key)) {
                    return self::$node_data[$doc_hash][self::getElementId($node)]->$key;
                }
            }
            if ($key === null) { // object with all data
                $data = array();
                foreach ($node->attributes as $attr) {
                    if (strpos($attr->nodeName, 'data-') === 0) {
                        $val = $attr->nodeValue[0] === '{' ? json_decode($attr->nodeValue) : $attr->nodeValue;
                        $data[substr($attr->nodeName, 5)] = $val;
                    }
                }
                return (object) $data;
            }
            if ($data = $node->getAttribute('data-' . $key)) {
                $val = $data[0] === '{' ? json_decode($data) : $data;
                return $val;
            }
        }

        return null;
    }

    /**
     * Remove a previously-stored piece of data.
     *
     * @param  string|string[]  $name
     *
     * @return $this
     */
    public function removeData($name = null)
    {
        $remove_names = \is_array($name) ? $name : explode(' ', $name);
        $doc_hash = spl_object_hash($this->document);

        if ( ! isset(self::$node_data[$doc_hash])) {
            return $this;
        }

        foreach ($this->getElements() as $node) {
            if ( ! $node->hasAttribute('dqn_tmp_id')) {
                continue;
            }

            $node_id = self::getElementId($node);

            if (isset(self::$node_data[$doc_hash][$node_id])) {
                if ($name === null) {
                    self::$node_data[$doc_hash][$node_id] = null;
                } else {
                    foreach ($remove_names as $remove_name) {
                        if (isset(self::$node_data[$doc_hash][$node_id]->$remove_name)) {
                            self::$node_data[$doc_hash][$node_id]->$remove_name = null;
                        }
                    }
                }
            }
        }
        return $this;
    }

    /**
     * Convert css string to array
     *
     * @param  string containing style properties
     *
     * @return array with name-value as style properties
     */
    private static function parseStyle(string $css)
    {
        $statements = explode(';', preg_replace('/\s+/s', ' ', $css));
        $styles = array();

        foreach ($statements as $statement) {
            if ($p = strpos($statement, ':')) {
                $key = trim(substr($statement, 0, $p));
                $value = trim(substr($statement, $p + 1));
                $styles[$key] = $value;
            }
        }

        return $styles;
    }

    /**
     * Convert css name-value array to string
     *
     * @param  array with style properties
     *
     * @return string containing style properties
     */
    private static function getStyle(array $array)
    {
        $styles = '';
        foreach ($array as $key => $value) {
            $styles .= $key . ': ' . $value . ';';
        }
        return $styles;
    }

    /**
     * Get the value of a computed style property for the first element in the set of matched elements
     * or set one or more CSS properties for every matched element.
     *
     * @param  string  $name
     * @param  string  $val
     *
     * @return $this|string
     */
    public function css(string $name, $val = null)
    {
        if ($val !== null) { // set css for all nodes
            foreach ($this->getElements() as $node) {
                $style = self::parseStyle($node->getAttribute('style'));
                $style[$name] = $val;
                $node->setAttribute('style', self::getStyle($style));
            }
            return $this;
        }
        if ($node = $this->getFirstElmNode()) { // get css value for first element
            $style = self::parseStyle($node->getAttribute('style'));
            if (isset($style[$name])) {
                return $style[$name];
            }
        }

        return null;
    }

    /**
     * Remove an attribute from each element in the set of matched elements.
     * Name can be a space-separated list of attributes.
     *
     * @param  string|string[]  $name
     *
     * @return $this
     */
    public function removeAttr($name)
    {
        $remove_names = \is_array($name) ? $name : explode(' ', $name);

        foreach ($this->getElements() as $node) {
            foreach ($remove_names as $remove_name) {
                $node->removeAttribute($remove_name);
            }
        }

        return $this;
    }

    /**
     * Adds the specified class(es) to each element in the set of matched elements.
     *
     * @param  string|string[]  $class_name  class name(s)
     *
     * @return $this
     */
    public function addClass($class_name)
    {
        $add_names = \is_array($class_name) ? $class_name : explode(' ', $class_name);

        foreach ($this->getElements() as $node) {
            $node_classes = array();
            if ($node_class_attr = $node->getAttribute('class')) {
                $node_classes = explode(' ', $node_class_attr);
            }
            foreach ($add_names as $add_name) {
                if ( ! \in_array($add_name, $node_classes, true)) {
                    $node_classes[] = $add_name;
                }
            }
            if (\count($node_classes) > 0) {
                $node->setAttribute('class', implode(' ', $node_classes));
            }
        }

        return $this;
    }

    /**
     * Determine whether any of the matched elements are assigned the given class.
     *
     * @param  string  $class_name
     *
     * @return boolean
     */
    public function hasClass($class_name)
    {
        foreach ($this->nodes as $node) {
            if ($node instanceof \DOMElement && $node_class_attr = $node->getAttribute('class')) {
                $node_classes = explode(' ', $node_class_attr);
                if (\in_array($class_name, $node_classes, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Remove a single class, multiple classes, or all classes from each element in the set of matched elements.
     *
     * @param  string|string[]  $class_name
     *
     * @return $this
     */
    public function removeClass($class_name = '')
    {
        $remove_names = \is_array($class_name) ? $class_name : explode(' ', $class_name);

        foreach ($this->nodes as $node) {
            if ($node instanceof \DOMElement && $node->hasAttribute('class')) {
                $node_classes = preg_split('#\s+#s', $node->getAttribute('class'));
                $class_removed = false;

                if ($class_name === '') { // remove all
                    $node_classes = array();
                    $class_removed = true;
                } else {
                    foreach ($remove_names as $remove_name) {
                        $key = array_search($remove_name, $node_classes, true);
                        if ($key !== false) {
                            unset($node_classes[$key]);
                            $class_removed = true;
                        }
                    }
                }
                if ($class_removed) {
                    $node->setAttribute('class', implode(' ', $node_classes));
                }
            }
        }

        return $this;
    }

    /**
     * Remove a single class, multiple classes, or all classes from each element in the set of matched elements.
     *
     * @param  string|string[]  $class_name
     *
     * @return $this
     */
    public function toggleClass($class_name = '')
    {
        $toggle_names = \is_array($class_name) ? $class_name : explode(' ', $class_name);

        foreach ($this as $node) {
            foreach ($toggle_names as $toggle_class) {
                if ( ! $node->hasClass($toggle_class)) {
                    $node->addClass($toggle_class);
                } else {
                    $node->removeClass($toggle_class);
                }
            }
        }

        return $this;
    }

    /**
     * Get the value of a property for the first element in the set of matched elements
     * or set one or more properties for every matched element.
     *
     * @param  string  $name
     * @param  string  $val
     *
     * @return $this|mixed|null
     */
    public function prop(string $name, $val = null)
    {
        if ($val !== null) { // set attribute for all nodes
            foreach ($this->nodes as $node) {
                $node->$name = $val;
            }

            return $this;
        }
        // get property value for first element
        if ($name === 'outerHTML') {
            return $this->getOuterHtml();
        }
        if ($node = $this->getFirstElmNode()) {
            if (isset($node->$name)) {
                return $node->$name;
            }
        }
        return null;
    }

    /**
     * Get the children of each element in the set of matched elements, including text and comment nodes.
     *
     * @return $this
     */
    public function contents()
    {
        return $this->children(false);
    }

    /* @noinspection PhpDocMissingThrowsInspection */
    /**
     * Get the children of each element in the set of matched elements, optionally filtered by a selector.
     *
     * @param  string|self|callable|DOMNodeList|DOMNode|false|null  $selector  expression that filters the set of matched elements
     *
     * @return $this
     */
    public function children($selector = null)
    {
        $result = $this->createChildInstance();

        if ( ! isset($this->document) || $this->length <= 0) {
            return $result;
        }

        if (isset($this->root_instance) || $this->getXpathQuery()) {
            foreach ($this->nodes as $node) {
                if ($node->hasChildNodes()) {
                    /* @noinspection PhpUnhandledExceptionInspection */
                    $result->loadDomNodeList($node->childNodes);
                }
            }
        } else {
            /* @noinspection PhpUnhandledExceptionInspection */
            $result->loadDomNodeList($this->document->childNodes);
        }

        if ($selector !== false) { // filter out text nodes
            $filtered_elements = array();
            foreach ($result->getElements() as $result_elm) {
                $filtered_elements[] = $result_elm;
            }
            $result->nodes = $filtered_elements;
            $result->length = \count($result->nodes);
        }

        if ($selector) {
            $result = $result->filter($selector);
        }

        return $result;
    }

    /**
     * Get the siblings of each element in the set of matched elements, optionally filtered by a selector.
     *
     * @param  string|self|callable|DOMNodeList|DOMNode|null  $selector  expression that filters the set of matched elements
     *
     * @return $this
     */
    public function siblings($selector = null)
    {
        $result = $this->createChildInstance();

        if (isset($this->document) && $this->length > 0) {
            foreach ($this->nodes as $node) {
                if ($node->parentNode) {
                    foreach ($node->parentNode->childNodes as $sibling) {
                        if ($sibling instanceof \DOMElement && ! $sibling->isSameNode($node)) {
                            $result->addDomNode($sibling);
                        }
                    }
                }
            }

            if ($selector) {
                $result = $result->filter($selector);
            }
        }

        return $result;
    }

    /**
     * Get the parent of each element in the current set of matched elements, optionally filtered by a selector
     *
     * @param  string|self|callable|DOMNodeList|DOMNode|null  $selector  expression that filters the set of matched elements
     *
     * @return $this
     */
    public function parent($selector = null)
    {
        /* @var DomQuery $resule */
        $result = $this->createChildInstance();

        if (isset($this->document) && $this->length > 0) {
            foreach ($this->nodes as $node) {
                if ($node->parentNode) {
                    $result->addDomNode($node->parentNode);
                }
            }

            if ($selector) {
                $result = $result->filter($selector);
            }
        }

        return $result;
    }

    /**
     * For each element in the set, get the first element that matches the selector
     * by testing the element itself and traversing up through its ancestors in the DOM tree.
     *
     * @param  string|self|callable|DOMNodeList|DOMNode  $selector  selector expression to match elements against
     *
     * @return $this
     */
    public function closest($selector)
    {
        $result = $this->createChildInstance();

        if ( ! isset($this->document) || $this->length <= 0) {
            return $result;
        }

        foreach ($this->nodes as $node) {
            $current = $node;

            while ($current instanceof \DOMElement) {
                if (self::create($current)->is($selector)) {
                    $result->addDomNode($current);
                    break;
                }
                $current = $current->parentNode;
            }
        }


        return $result;
    }

    /**
     * Remove elements from the set of matched elements.
     *
     * @param  string|self|callable|DOMNodeList|DOMNode  $selector
     *
     * @return $this
     */
    public function not($selector)
    {
        $result = $this->createChildInstance();

        if ($this->length > 0) {
            if (\is_callable($selector)) {
                foreach ($this->nodes as $index => $node) {
                    if ( ! $selector($node, $index)) {
                        $result->addDomNode($node);
                    }
                }
            } else {
                $selection = self::create($this->document)->find($selector);

                if ($selection->length > 0) {
                    foreach ($this->nodes as $node) {
                        $matched = false;
                        foreach ($selection as $result_node) {
                            /* @var DOMNode $result_node */
                            if ($result_node->isSameNode($node)) {
                                $matched = true;
                                break 1;
                            }
                        }
                        if ( ! $matched) {
                            $result->addDomNode($node);
                        }
                    }
                } else {
                    $result->addNodes($this->nodes);
                }
            }
        }

        return $result;
    }

    /**
     * Reduce the set of matched elements to those that match the selector
     *
     * @param  string|self|callable|DOMNodeList|DOMNode  $selector
     * @param  string|self|DOMNodeList|DOMNode|\DOMDocument  $context
     *
     * @return $this
     */
    public function add($selector, $context = null)
    {
        $result = $this->createChildInstance();
        $result->nodes = $this->nodes;

        $selection = $this->getTargetResult($selector, $context);

        foreach ($selection as $selection_node) {
            if ( ! $result->is($selection_node)) {
                if ($result->document === $selection_node->document) {
                    $new_node = $selection_node->get(0);
                } else {
                    $new_node = $this->document->importNode($selection_node->get(0), true);
                }

                $result->addDomNode($new_node);
            }
        }

        return $result;
    }

    /**
     * Reduce the set of matched elements to those that match the selector
     *
     * @param  string|self|callable|DOMNodeList|DOMNode  $selector
     *
     * @return $this
     */
    public function filter($selector)
    {
        /* @var DomQuery $result */
        $result = $this->createChildInstance();

        if ($this->length > 0) {
            if (\is_callable($selector)) {
                foreach ($this->nodes as $index => $node) {
                    if ($selector($node, $index)) {
                        $result->addDomNode($node);
                    }
                }
            } else {
                $selection = self::create($this->document)->find($selector);

                foreach ($selection as $result_node) {
                    /* @var DOMNode $result_node */
                    foreach ($this->nodes as $node) {
                        if ($result_node->isSameNode($node)) {
                            $result->addDomNode($node);
                            break 1;
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Create a deep copy of the set of matched elements (does not clone attached data).
     *
     * @return $this
     */
    public function clone()
    {
        return $this->createChildInstance($this->getClonedNodes());
    }

    /**
     *  Get the position of the first element within the DOM, relative to its sibling elements.
     *  Or get the position of the first node in the result that matches the selector.
     *
     * @param  string|self|callable|DOMNodeList|DOMNode  $selector
     *
     * @return int $position
     */
    public function index($selector = null)
    {
        if ($selector === null) {
            if ($node = $this->getFirstElmNode()) {
                $position = 0;
                while ($node && ($node = $node->previousSibling)) {
                    if ($node instanceof \DOMElement) {
                        $position++;
                    } else {
                        break;
                    }
                }
                return $position;
            }
        } else {
            foreach ($this as $key => $node) {
                if ($node->is($selector)) {
                    return $key;
                }
            }
        }

        return -1;
    }

    /**
     * Check if any node matches the selector
     *
     * @param  string|self|callable|DOMNodeList|DOMNode  $selector
     *
     * @return boolean
     */
    public function is($selector)
    {
        if ($this->length > 0) {
            if (\is_callable($selector)) {
                foreach ($this->nodes as $index => $node) {
                    if ($selector($node, $index)) {
                        return true;
                    }
                }
            } else {
                $selection = self::create($this->document)->find($selector);

                foreach ($selection->nodes as $result_node) {
                    foreach ($this->nodes as $node) {
                        if ($result_node->isSameNode($node)) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * Reduce the set of matched elements to those that have a descendant that matches the selector
     *
     * @param  string|self|callable|DOMNodeList|DOMNode  $selector
     *
     * @return $this
     */
    public function has($selector)
    {
        /* @var DomQuery $result */
        $result = $this->createChildInstance();

        if ($this->length > 0) {
            foreach ($this as $node) {
                if ($node->find($selector)->length > 0) {
                    $result->addDomNode($node->get(0));
                }
            }
        }

        return $result;
    }

    /**
     * Reduce the set of matched elements to a subset specified by the offset and length (php like)
     *
     * @param  integer  $offset
     * @param  integer  $length
     *
     * @return $this
     */
    public function slice($offset = 0, $length = null)
    {
        $result = $this->createChildInstance();
        $result->nodes = \array_slice($this->nodes, $offset, $length);
        $result->length = \count($result->nodes);
        return $result;
    }

    /**
     * Reduce the set of matched elements to the one at the specified index.
     *
     * @param  integer  $index
     *
     * @return $this
     */
    public function eq($index)
    {
        return $this->slice($index, 1);
    }

    /**
     * Returns DomQuery with first node
     *
     * @param  string|self|callable|DOMNodeList|DOMNode|null  $selector  expression that filters the set of matched elements
     *
     * @return $this
     */
    public function first($selector = null)
    {
        $result = $this[0];
        if ($selector) {
            $result = $result->filter($selector);
        }
        return $result;
    }

    /**
     * Returns DomQuery with last node
     *
     * @param  string|self|callable|DOMNodeList|DOMNode|null  $selector  expression that filters the set of matched elements
     *
     * @return $this
     */
    public function last($selector = null)
    {
        $result = $this[$this->length - 1];
        if ($selector) {
            $result = $result->filter($selector);
        }
        return $result;
    }

    /**
     * Returns DomQuery with immediately following sibling of all nodes
     *
     * @param  string|self|callable|DOMNodeList|DOMNode|null  $selector  expression that filters the set of matched elements
     *
     * @return $this
     */
    public function next($selector = null)
    {
        $result = $this->createChildInstance();

        if (isset($this->document) && $this->length > 0) {
            foreach ($this->nodes as $node) {
                if ($next = self::getNextElement($node)) {
                    $result->addDomNode($next);
                }
            }

            if ($selector) {
                $result = $result->filter($selector);
            }
        }

        return $result;
    }

    /**
     * Get all following siblings of each element in the set of matched elements, optionally filtered by a selector.
     *
     * @param  string|self|callable|DOMNodeList|DOMNode|null  $selector  expression that filters the set of matched elements
     *
     * @return $this
     */
    public function nextAll($selector = null)
    {
        $result = $this->createChildInstance();

        if (isset($this->document) && $this->length > 0) {
            foreach ($this->nodes as $node) {
                $current = $node;
                while ($next = self::getNextElement($current)) {
                    $result->addDomNode($next);
                    $current = $next;
                }
            }

            if ($selector) {
                $result = $result->filter($selector);
            }
        }

        return $result;
    }

    /**
     * Returns DomQuery with immediately preceding sibling of all nodes
     *
     * @param  string|self|callable|DOMNodeList|DOMNode|null  $selector  expression that filters the set of matched elements
     *
     * @return $this
     */
    public function prev($selector = null)
    {
        $result = $this->createChildInstance();

        if (isset($this->document) && $this->length > 0) {
            foreach ($this->nodes as $node) { // get all previous sibling of all nodes
                if ($prev = self::getPreviousElement($node)) {
                    $result->addDomNode($prev);
                }
            }

            if ($selector) {
                $result = $result->filter($selector);
            }
        }

        return $result;
    }

    /**
     * Get all preceding siblings of each element in the set of matched elements, optionally filtered by a selector.
     *
     * @param  string|self|callable|DOMNodeList|DOMNode|null  $selector  expression that filters the set of matched elements
     *
     * @return $this
     */
    public function prevAll($selector = null)
    {
        $result = $this->createChildInstance();

        if (isset($this->document) && $this->length > 0) {
            foreach ($this->nodes as $node) {
                $current = $node;
                while ($prev = self::getPreviousElement($current)) {
                    $result->addDomNode($prev, true);
                    $current = $prev;
                }
            }

            if ($selector) {
                $result = $result->filter($selector);
            }
        }

        return $result;
    }

    /**
     * Remove the set of matched elements
     *
     * @param  string|self|callable|DOMNodeList|DOMNode|null  $selector  expression that
     * filters the set of matched elements to be removed
     *
     * @return $this
     */
    public function remove($selector = null)
    {
        $result = $this;
        if ($selector) {
            $result = $result->filter($selector);
        }
        foreach ($result->nodes as $node) {
            if ($node->parentNode) {
                $node->parentNode->removeChild($node);
            }
        }

        $result->nodes = array();
        $result->length = 0;

        return $result;
    }

    /**
     * Empty Dom
     *
     * @return $this
     */
    public function empty()
    {
        $this->remove();
        return $this;
    }

    /**
     * Check if is empty
     *
     * @return bool
     */
    public function isEmpty()
    {
        return empty($this->document);
    }

    /**
     * Import nodes and insert or append them via callback function
     *
     * @param  string[]|self[]|array  $contents
     * @param  callable  $import_function
     *
     * @return DOMNode[] $imported_nodes
     */
    private function importNodes($contents, callable $import_function)
    {
        /* @var DOMNode[] */
        $imported_nodes = [];

        foreach ($contents as $content) {

            if (\is_string($content) && strpos($content, "\n") !== false) {
                $this->preserve_no_newlines = false;
                if (isset($this->root_instance)) {
                    $this->root_instance->preserve_no_newlines = false;
                }
            }

            if ( ! ($content instanceof self)) {
                $content = new self($content);
            }

            foreach ($this->nodes as $node) {
                foreach ($content->getNodes() as $content_node) {
                    if ($content_node->ownerDocument === $node->ownerDocument) {
                        $imported_node = $content_node->cloneNode(true);
                    } else {
                        $imported_node = $this->document->importNode($content_node, true);
                    }
                    $imported_node = $import_function($node, $imported_node);
                    if ($imported_node instanceof DOMNode) {
                        $imported_nodes[] = $imported_node;
                    }
                }
            }
        }

        return $imported_nodes;
    }

    /**
     * Get target result using selector or instance of self
     *
     * @param  string|self  $target
     * @param  string|self|DOMNodeList|DOMNode|\DOMDocument  $context
     *
     * @return $this
     */
    private function getTargetResult($target, $context = null)
    {
        if ($context === null && \is_string($target) && strpos($target, '<') === false) {
            $context = $this->document;
        }

        return $context === null ? self::create($target) : self::create($target, $context);
    }

    /**
     * Insert content to the end of each element in the set of matched elements.
     *
     * @param  string[]|self[]|array  $contents
     *
     * @return $this
     */
    public function append(...$contents)
    {
        $this->importNodes($contents, function ($node, $imported_node) {
            /* @var DOMNode $node */
            $node->appendChild($imported_node);
        });

        return $this;
    }

    /**
     * Insert every element in the set of matched elements to the end of the target.
     *
     * @param  string|self  $target
     *
     * @return $this
     */
    public function appendTo($target)
    {
        $target_result = $this->getTargetResult($target);

        $nodes = $target_result->importNodes($this, function ($node, $imported_node) {
            /* @var DOMNode $node */
            return $node->appendChild($imported_node);
        });

        $this->remove();

        return $target_result->find($nodes);
    }

    /**
     * Insert content to the beginning of each element in the set of matched elements
     *
     * @param  string[]|self[]|array  $contents
     *
     * @return $this
     */
    public function prepend(...$contents)
    {
        $this->importNodes($contents, function ($node, $imported_node) {
            /* @var DOMNode $node */
            $node->insertBefore($imported_node, $node->childNodes->item(0));
        });

        return $this;
    }

    /**
     * Insert every element in the set of matched elements to the beginning of the target.
     *
     * @param  string|self  $target
     *
     * @return $this
     */
    public function prependTo($target)
    {
        $target_result = $this->getTargetResult($target);

        $nodes = $target_result->importNodes($this, function ($node, $imported_node) {
            /* @var DOMNode $node */
            return $node->insertBefore($imported_node, $node->childNodes->item(0));
        });

        $this->remove();

        return $target_result->find($nodes);
    }

    /**
     * Insert content before each element in the set of matched elements.
     *
     * @param  string[]|self[]|array  $contents
     *
     * @return $this
     */
    public function before(...$contents)
    {
        $this->importNodes($contents, function ($node, $imported_node) {
            if ($node->parentNode instanceof \DOMDocument) {
                throw new \Exception('Can not set before root element ' . $node->tagName . ' of document');
            }

            $node->parentNode->insertBefore($imported_node, $node);
        });

        return $this;
    }

    /**
     * Insert content after each element in the set of matched elements.
     *
     * @param  string[]|self[]|array  $contents
     *
     * @return $this
     */
    public function after(...$contents)
    {
        $this->importNodes($contents, function ($node, $imported_node) {
            if ($node->nextSibling) {
                $node->parentNode->insertBefore($imported_node, $node->nextSibling);
            } else { // node is last, so there is no next sibling to insert before
                $node->parentNode->appendChild($imported_node);
            }
        });

        return $this;
    }

    /**
     * Replace each element in the set of matched elements with the provided
     * new content and return the set of elements that was removed.
     *
     * @param  string[]|self[]|array  $new_contents
     *
     * @return $this
     */
    public function replaceWith(...$new_contents)
    {
        $removed_nodes = new self();

        $this->importNodes($new_contents, function ($node, $imported_node) use (&$removed_nodes) {
            if ($node->nextSibling) {
                $node->parentNode->insertBefore($imported_node, $node->nextSibling);
            } else { // node is last, so there is no next sibling to insert before
                $node->parentNode->appendChild($imported_node);
            }
            $removed_nodes->addDomNode($node);
            $node->parentNode->removeChild($node);
        });

        foreach ($new_contents as $new_content) {
            if ( ! \is_string($new_content)) {
                self::create($new_content)->remove();
            }
        }

        return $removed_nodes;
    }

    /**
     * Wrap an HTML structure around each element in the set of matched elements
     *
     * @param  string[]|self[]|array  $contents
     *
     * @return $this
     */
    public function wrap(...$contents)
    {
        $this->importNodes($contents, function ($node, $imported_node) {
            /* @var DOMNode $imported_node */
            if ($node->parentNode instanceof \DOMDocument) {
                throw new \Exception('Can not wrap inside root element ' . $node->tagName . ' of document');
            }

            // replace node with imported wrapper
            $old = $node->parentNode->replaceChild($imported_node, $node);
            // old node goes inside the most inner child of wrapper
            $target = $imported_node;
            while ($target->hasChildNodes()) {
                $target = $target->childNodes[0];
            }
            $target->appendChild($old);
        });

        return $this;
    }

    /**
     * Wrap an HTML structure around all elements in the set of matched elements
     *
     * @param  string[]|self[]|array  $contents
     *
     * @return $this
     */
    public function wrapAll(...$contents)
    {
        /* @var DOMNode $wrapper_node */
        $wrapper_node = null; // node given as wrapper
        /* @var DOMNode $wrap_target_node */
        $wrap_target_node = null; // node that wil be parent of content to be wrapped

        $this->importNodes($contents, function ($node, $imported_node) use (&$wrapper_node, &$wrap_target_node) {
            /* @var DOMNode $imported_node */
            if ($node->parentNode instanceof \DOMDocument) {
                throw new \Exception('Can not wrap inside root element ' . $node->tagName . ' of document');
            }
            if ($wrapper_node && $wrap_target_node) { // already wrapped before
                $old = $node->parentNode->removeChild($node);
                $wrap_target_node->appendChild($old);
            } else {
                $wrapper_node = $imported_node;
                // replace node with (imported) wrapper
                $old = $node->parentNode->replaceChild($imported_node, $node);
                // old node goes inside the most inner child of wrapper
                $target = $imported_node;

                while ($target->hasChildNodes()) {
                    $target = $target->childNodes[0];
                }
                $target->appendChild($old);
                $wrap_target_node = $target; // save for next round
            }
        });

        return $this;
    }

    /**
     * Wrap an HTML structure around the content of each element in the set of matched elements
     *
     * @param  string[]|self[]|array  $contents
     *
     * @return $this
     */
    public function wrapInner(...$contents)
    {
        foreach ($this->nodes as $node) {
            self::create($node->childNodes)->wrapAll(...$contents);
        }

        return $this;
    }

    /**
     * Remove the parents of the set of matched elements from the DOM, leaving the matched elements in their place.
     *
     * @return DomQuery Parent node
     */
    public function unwrap()
    {
        if ( ! isset($this->document) || $this->length <= 0) {
            return $this;
        }

        $result = $this->createChildInstance();

        /** @var DOMNode $parentNode */
        $parentNode = null;

        /** @var DOMNode $node */
        foreach ($this->nodes as $node) {

            if ($node instanceof \DOMDocument) {
                unset($result);
                return $this;
            }

            $current = $node;

            $parentNode = $current->parentNode;

            while ($current->hasChildNodes()) {
                /** @var DOMNode $child */
                foreach ($current->childNodes as $child) {
                    $parentNode->appendChild($child);
                }
            }

            $parentNode->removeChild($node);
            $result->addDomNode($parentNode);
        }

        return $result;
    }

    /**
     * Check if property exist for this instance
     *
     * @param  string  $name
     *
     * @return boolean
     */
    public function __isset($name)
    {
        return $this->__get($name) !== null;
    }

    public function __get($name)
    {
        switch ($name) {
            case 'text':
            case 'plaintext':
                return $this->text();
                break;
            case 'innerHTML':
                return $this->getInnerHtml();
                break;
            case 'outerHTML':
                return $this->getOuterHtml();
                break;
        }

        return parent::__get($name);
    }
}
