<?php

namespace Rct567\DomQuery\Tests;

use Rct567\DomQuery\DomQuery;

class DomQueryManipulationTest extends \PHPUnit\Framework\TestCase
{
    /*
     * Test remove
     */
    public function testRemove()
    {
        $dom = DomQuery::create('<div><a title="hello">Some text</a><a>B</a><span>C</span></div>');
        $dom->find('a')->remove();
        $this->assertEquals("<div><span>C</span></div>", (string) $dom);
    }

     /*
     * Test remove with selector filter
     */
    public function testRemoveWithSelectorFilter()
    {
        $dom = DomQuery::create('<div><a title="hello">Some text</a><a>B</a><span>C</span></div>');
        $dom->find('a')->remove('[title]');
        $this->assertEquals("<div><a>B</a><span>C</span></div>", (string) $dom);
    }

    /*
     * Test change text
     */
    public function testMultipleNodesTextChange()
    {
        $dom = DomQuery::create('<div><a title="hello">Some text</a><a>B</a><span>C</span></div>');
        $dom->find('a')->text('Changed text');
        $this->assertEquals("<div><a title=\"hello\">Changed text</a><a>Changed text</a><span>C</span></div>", (string) $dom);
    }

     /*
     * Test append html
     */
    public function testAppend()
    {
        // append string with html
        $dom = new DomQuery('<a></a><p></p><b></b><a></a>');
        $dom->find('a')->append('<span></span>');
        $this->assertEquals('<a><span></span></a><p></p><b></b><a><span></span></a>', (string) $dom);

        // append DomQuery instance
        $dom = new DomQuery('<a></a><p></p><b></b><a></a>');
        $dom->find('a')->append(DomQuery::create('<i>X</i>'));
        $this->assertEquals('<a><i>X</i></a><p></p><b></b><a><i>X</i></a>', (string) $dom);
        $this->assertEquals('X', $dom->find('i')->text());
    }

    /*
     * Test prepend html
     */
    public function testPrepend()
    {
        $dom = new DomQuery('<a>X</a>');
        $dom->find('a')->prepend('<span></span>', '<i></i>');
        $this->assertEquals('<a><i></i><span></span>X</a>', (string) $dom);
        $this->assertEquals(1, $dom->find('span')->length);
    }

    /*
     * Test insert before
     */
    public function testBefore()
    {
        $dom = new DomQuery('<div> <a>X</a> </div>');
        $dom->find('a')->before('<span></span>');
        $this->assertEquals('<div> <span></span><a>X</a> </div>', (string) $dom);
        $this->assertEquals(1, $dom->find('span')->length);
    }

    /*
     * Test insert after
     */
    public function testAfter()
    {
        $dom = new DomQuery('<div> <a>X</a> </div>');
        $dom->find('a')->after('<span></span>');
        $this->assertEquals('<div> <a>X</a><span></span> </div>', (string) $dom);
        $this->assertEquals(1, $dom->find('span')->length);
    }

    /*
     * Test get html
     */
    public function testGetHtml()
    {
        $dom = new DomQuery('<p> <a>M<i>A</i></a> <span></span></p>');
        $this->assertEquals('M<i>A</i>', $dom->find('a')->html()); // inner
        $this->assertEquals('<a>M<i>A</i></a>', $dom->find('a')->prop('outerHTML')); // outer
        $this->assertEquals('A', $dom->find('a i')->html());
    }

    /*
     * Test set html
     */
    public function testSetHtml()
    {
        $dom = new DomQuery('<p> <a>M<i>A</i></a> <span></span> </p>');
        $dom->find('a')->html('<i>x</i>');
        $this->assertEquals('<p> <a><i>x</i></a> <span></span> </p>', (string) $dom);
    }
}