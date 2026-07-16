<?php

namespace dokuwiki\plugin\pluginrepo\test;

use DokuWikiTest;

/**
 * Tests for the pluginrepo repository helper
 *
 * @group plugin_pluginrepo
 * @group plugins
 */
class RepositoryTest extends DokuWikiTest
{
    protected $pluginsEnabled = ['pluginrepo'];

    /**
     * Get the repository helper
     *
     * @return \helper_plugin_pluginrepo_repository
     */
    protected function getHelper()
    {
        /** @var \helper_plugin_pluginrepo_repository $repo */
        $repo = plugin_load('helper', 'pluginrepo_repository');
        return $repo;
    }

    /**
     * Data provider for testIsValidExtensionID
     *
     * @return array [id, expected]
     */
    public function provideExtensionIDs()
    {
        return [
            ['sprintdoc', true],
            ['template:sprintdoc', true],
            ['foo_bar', true],
            ['foo-bar', true],
            ['foo.bar', true],
            ['foo123', true],
            ['sprintdoc template', false],
            ['config:sprintdoc', false],
            ['plugin:sprintdoc', false],
            ['template:sprintdoc template', false],
            ['../evil', false],
            ['', false],
            ['-leading', false],
        ];
    }

    /**
     * @dataProvider provideExtensionIDs
     * @param string $id
     * @param bool $expected
     */
    public function testIsValidExtensionID($id, $expected): void
    {
        $this->assertSame($expected, $this->getHelper()->isValidExtensionID($id));
    }

    /**
     * Invalid references must be dropped, valid ones kept and reindexed
     */
    public function testFilterValidExtensionIDs(): void
    {
        $input = ['sprintdoc', 'sprintdoc template', 'template:x', 'config:y', ''];
        $this->assertSame(
            ['sprintdoc', 'template:x'],
            $this->getHelper()->filterValidExtensionIDs($input)
        );
    }

    /**
     * Harmonizing must normalize namespaces and drop malformed references
     */
    public function testHarmonizeExtensionIDs(): void
    {
        $data = [
            'type' => '',
            'depends' => 'foo, bar',
            'conflicts' => 'sprintdoc template, template:sprintdoc, config:evil, good_one',
            'similar' => '',
        ];
        $this->getHelper()->harmonizeExtensionIDs($data);

        $this->assertSame('foo,bar', $data['depends']);
        $this->assertSame('template:sprintdoc,good_one', $data['conflicts']);
        $this->assertSame('', $data['similar']);
    }

    /**
     * Bare references on a template page must gain the template namespace
     */
    public function testHarmonizeExtensionIDsTemplate(): void
    {
        $data = [
            'type' => 'template',
            'depends' => 'foo',
            'conflicts' => '',
            'similar' => '',
        ];
        $this->getHelper()->harmonizeExtensionIDs($data);

        $this->assertSame('template:foo', $data['depends']);
    }
}
