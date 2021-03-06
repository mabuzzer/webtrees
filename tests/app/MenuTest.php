<?php
/**
 * webtrees: online genealogy
 * Copyright (C) 2018 webtrees development team
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
declare(strict_types=1);

namespace Fisharebest\Webtrees;

use Fisharebest\Webtrees\Menu;

/**
 * Test harness for the class Menu
 */
class MenuTest extends \Fisharebest\Webtrees\TestCase
{
    /**
     * Prepare the environment for these tests.
     *
     * @return void
     */
    public function setUp()
    {
    }

    /**
     * Test the constructor with default parameters.
     *
     * @return void
     */
    public function testConstructorDefaults()
    {
        $menu = new Menu('Test!');

        $this->assertSame('Test!', $menu->getLabel());
        $this->assertSame('#', $menu->getLink());
        $this->assertSame('', $menu->getClass());
        $this->assertSame([], $menu->getAttrs());
        $this->assertSame([], $menu->getSubmenus());
    }

    /**
     * Test the constructor with non-default parameters.
     *
     * @return void
     */
    public function testConstructorNonDefaults()
    {
        $submenus = [new Menu('Submenu')];
        $menu     = new Menu('Test!', 'link.html', 'link-class', ['foo' => 'bar'], $submenus);

        $this->assertSame('Test!', $menu->getLabel());
        $this->assertSame('link.html', $menu->getLink());
        $this->assertSame('link-class', $menu->getClass());
        $this->assertSame(['foo' => 'bar'], $menu->getAttrs());
        $this->assertSame($submenus, $menu->getSubmenus());
    }

    /**
     * Test the getter/setter for the label.
     *
     * @return void
     */
    public function testGetterSetterLabel()
    {
        $menu = new Menu('Test!');

        $return = $menu->setLabel('Label');

        $this->assertSame($return, $menu);
        $this->assertSame('Label', $menu->getLabel());
    }

    /**
     * Test the getter/setter for the link.
     *
     * @return void
     */
    public function testGetterSetterLink()
    {
        $menu = new Menu('Test!');

        $return = $menu->setLink('link.html');

        $this->assertSame($return, $menu);
        $this->assertSame('link.html', $menu->getLink());
    }

    /**
     * Test the getter/setter for the ID.
     *
     * @return void
     */
    public function testGetterSetterId()
    {
        $menu = new Menu('Test!');

        $return = $menu->setClass('link-class');

        $this->assertSame($return, $menu);
        $this->assertSame('link-class', $menu->getClass());
    }

    /**
     * Test the getter/setter for the Attrs event.
     *
     * @return void
     */
    public function testGetterSetterAttrs()
    {
        $menu = new Menu('Test!');

        $return = $menu->setAttrs(['foo' => 'bar']);

        $this->assertSame($return, $menu);
        $this->assertSame(['foo' => 'bar'], $menu->getAttrs());
    }

    /**
     * Test the getter/setter for the submenus.
     *
     * @return void
     */
    public function testGetterSetterSubmenus()
    {
        $menu     = new Menu('Test!');
        $submenus = [
            new Menu('Sub1'),
            new Menu('Sub2'),
        ];

        $return = $menu->setSubmenus($submenus);

        $this->assertSame($return, $menu);
        $this->assertSame($submenus, $menu->getSubmenus());
    }

    /**
     * Test the list rendering for a simple link.
     *
     * @return void
     */
    public function testFormatAsList()
    {
        $menu = new Menu('Test!', 'link.html');

        $this->assertSame('<li class=""><a href="link.html">Test!</a></li>', $menu->getMenuAsList());
    }

    /**
     * Test the list rendering for a simple link with a CSS ID.
     *
     * @return void
     */
    public function testFormatAsListWithClass()
    {
        $menu = new Menu('Test!', 'link.html', 'link-class');

        $this->assertSame('<li class="link-class"><a href="link.html">Test!</a></li>', $menu->getMenuAsList());
    }

    /**
     * Test the list rendering for an empty target.
     *
     * @return void
     */
    public function testFormatAsListWithNoTarget()
    {
        $menu = new Menu('Test!', '');

        $this->assertSame('<li class=""><a>Test!</a></li>', $menu->getMenuAsList());
    }

    /**
     * Test the list rendering for a default (hash) target.
     *
     * @return void
     */
    public function testFormatAsListWithHashTarget()
    {
        $menu = new Menu('Test!');

        $this->assertSame('<li class=""><a href="#">Test!</a></li>', $menu->getMenuAsList());
    }

    /**
     * Test the list rendering for an onclick link.
     *
     * @return void
     */
    public function testFormatAsListWithAttrs()
    {
        $menu = new Menu('Test!', '#', '', ['foo' => 'bar']);

        $this->assertSame('<li class=""><a href="#" foo="bar">Test!</a></li>', $menu->getMenuAsList());
    }

    /**
     * Test the list rendering for an onclick link.
     *
     * @return void
     */
    public function testFormatAsListWithAttrsAndId()
    {
        $menu = new Menu('Test!', '#', 'link-class', ['foo' => 'bar']);

        $this->assertSame('<li class="link-class"><a href="#" foo="bar">Test!</a></li>', $menu->getMenuAsList());
    }
}
