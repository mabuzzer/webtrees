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

use Fisharebest\Webtrees\Module\ModuleBlockInterface;
use Fisharebest\Webtrees\Module\ModuleChartInterface;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleInterface;
use Fisharebest\Webtrees\Module\ModuleMenuInterface;
use Fisharebest\Webtrees\Module\ModuleReportInterface;
use Fisharebest\Webtrees\Module\ModuleSidebarInterface;
use Fisharebest\Webtrees\Module\ModuleTabInterface;
use Fisharebest\Webtrees\Module\ModuleThemeInterface;
use League\Flysystem\Exception;

/**
 * Functions for managing and maintaining modules.
 */
class Module
{
    // We use a list of core modules to help identify custom ones.
    const CORE_MODULES = [
        'GEDFact_assistant',
        'ahnentafel_report',
        'ancestors_chart',
        'batch_update',
        'bdm_report',
        'birth_report',
        'cemetery_report',
        'change_report',
        'charts',
        'ckeditor',
        'clippings',
        'compact_tree_chart',
        'death_report',
        'descendancy',
        'descendancy_chart',
        'descendancy_report',
        'extra_info',
        'fact_sources',
        'family_book_chart',
        'family_group_report',
        'family_nav',
        'fan_chart',
        'faq',
        'gedcom_block',
        'gedcom_favorites',
        'gedcom_news',
        'gedcom_stats',
        'hourglass_chart',
        'html',
        'individual_ext_report',
        'individual_report',
        'lifespans_chart',
        'lightbox',
        'logged_in',
        'login_block',
        'marriage_report',
        'media',
        'missing_facts_report',
        'notes',
        'occupation_report',
        'pedigree-map',
        'pedigree_chart',
        'pedigree_report',
        'personal_facts',
        'places',
        'random_media',
        'recent_changes',
        'relationships_chart',
        'relative_ext_report',
        'relatives',
        'review_changes',
        'sitemap',
        'sources_tab',
        'statistics_chart',
        'stories',
        'theme_select',
        'timeline_chart',
        'todays_events',
        'todo',
        'top10_givnnames',
        'top10_pageviews',
        'top10_surnames',
        'tree',
        'upcoming_events',
        'user_blog',
        'user_favorites',
        'user_messages',
        'user_welcome',
        'yahrzeit',
    ];

    /** @var ModuleInterface[] */
    private static $modules = [];

    /**
     * Load a module from a file.  Since third-party modules may declare classes or functions,
     * we must only load each file once.
     *
     * @param string $file
     *
     * @return ModuleInterface|null
     */
    private static function loadModule($file)
    {
        if (!array_key_exists($file, self::$modules)) {
            self::$modules[$file] = null;
            try {
                $module = include $file;
                if ($module instanceof ModuleInterface) {
                    self::$modules[$file] = $module;
                }
            } catch (Exception $ex) {
                DebugBar::addThrowable($ex);

                Log::addErrorLog($ex->getMessage());
            }
        }

        return self::$modules[$file];
    }

    /**
     * Get a list of all core modules.  We need to identify
     * third-party during upgrade and on the module admin page.
     *
     * @return string[]
     */
    public static function getCoreModuleNames(): array
    {
        return [
            'GEDFact_assistant',
            'ahnentafel_report',
            'ancestors_chart',
            'batch_update',
            'bdm_report',
            'birth_report',
            'cemetery_report',
            'change_report',
            'charts',
            //'ckeditor',
            'clippings',
            'compact_tree_chart',
            'death_report',
            'descendancy',
            'descendancy_chart',
            'descendancy_report',
            'extra_info',
            'fact_sources',
            'families',
            'family_book_chart',
            'family_group_report',
            'family_nav',
            'fan_chart',
            'faq',
            'gedcom_block',
            'gedcom_favorites',
            'gedcom_news',
            'gedcom_stats',
            'googlemap',
            'hourglass_chart',
            'html',
            'individual_ext_report',
            'individual_report',
            'individuals',
            'lifespans_chart',
            'lightbox',
            'logged_in',
            'login_block',
            'marriage_report',
            'media',
            'missing_facts_report',
            'notes',
            'occupation_report',
            'openstreetmap',
            'pedigree_chart',
            'pedigree_report',
            'personal_facts',
            'random_media',
            'recent_changes',
            'relationships_chart',
            'relative_ext_report',
            'relatives',
            'review_changes',
            'sitemap',
            'sources_tab',
            'statistics_chart',
            'stories',
            'theme_select',
            'timeline_chart',
            'todays_events',
            'todo',
            'top10_givnnames',
            'top10_pageviews',
            'top10_surnames',
            'tree',
            'upcoming_events',
            'user_blog',
            'user_favorites',
            'user_messages',
            'user_welcome',
            'yahrzeit',
        ];
    }

    /**
     * Get a list of all active (enabled) modules.
     *
     * @return ModuleInterface[]
     */
    private static function getActiveModules(): array
    {
        /** @var ModuleInterface[] - Only query the database once. */
        static $modules;

        if ($modules === null) {
            $module_names = Database::prepare(
                "SELECT module_name FROM `##module` WHERE status = 'enabled'"
            )->fetchOneColumn();

            $modules = [];
            foreach ($module_names as $module_name) {
                try {
                    $module = self::loadModule(WT_ROOT . WT_MODULES_DIR . $module_name . '/module.php');
                    if ($module instanceof ModuleInterface) {
                        $modules[$module->getName()] = $module;
                    } else {
                        throw new \Exception();
                    }
                } catch (\Exception $ex) {
                    DebugBar::addThrowable($ex);

                    // The module has been deleted or is broken? Disable it.
                    Log::addConfigurationLog("Module {$module_name} is missing or broken - disabling it. " . $ex->getMessage(), null);
                    Database::prepare(
                        "UPDATE `##module` SET status = 'disabled' WHERE module_name = :module_name"
                    )->execute([
                        'module_name' => $module_name,
                    ]);
                }
            }
        }

        return $modules;
    }

    /**
     * Get a list of modules which (a) provide a specific function and (b) we have permission to see.
     *
     * We cannot currently use auto-loading for modules, as there may be user-defined
     * modules about which the auto-loader knows nothing.
     *
     * @param Tree   $tree
     * @param string $component The type of module, such as "tab", "report" or "menu"
     *
     * @return ModuleBlockInterface[]|ModuleChartInterface[]|ModuleMenuInterface[]|ModuleReportInterface[]|ModuleSidebarInterface[]|ModuleTabInterface[]|ModuleThemeInterface[]
     */
    private static function getActiveModulesByComponent(Tree $tree, $component): array
    {
        $module_names = Database::prepare(
            "SELECT module_name" .
            " FROM `##module`" .
            " JOIN `##module_privacy` USING (module_name)" .
            " WHERE gedcom_id = :tree_id AND component = :component AND status = 'enabled' AND access_level >= :access_level" .
            " ORDER BY CASE component WHEN 'menu' THEN menu_order WHEN 'sidebar' THEN sidebar_order WHEN 'tab' THEN tab_order ELSE 0 END, module_name"
        )->execute([
            'tree_id'      => $tree->getTreeId(),
            'component'    => $component,
            'access_level' => Auth::accessLevel($tree),
        ])->fetchOneColumn();

        $array = [];
        foreach ($module_names as $module_name) {
            $interface = '\Fisharebest\Webtrees\Module\Module' . ucfirst($component) . 'Interface';
            $module    = self::getModuleByName($module_name);
            if ($module instanceof $interface) {
                $array[$module_name] = $module;
            }
        }

        // The order of menus/sidebars/tabs is defined in the database. Others are sorted by name.
        if ($component !== 'menu' && $component !== 'sidebar' && $component !== 'tab') {
            uasort($array, function (ModuleInterface $x, ModuleInterface $y): int {
                return I18N::strcasecmp($x->getTitle(), $y->getTitle());
            });
        }

        return $array;
    }

    /**
     * Get a list of all modules, enabled or not, which provide a specific function.
     *
     * We cannot currently use auto-loading for modules, as there may be user-defined
     * modules about which the auto-loader knows nothing.
     *
     * @param string $component The type of module, such as "tab", "report" or "menu"
     *
     * @return ModuleInterface[]
     */
    public static function getAllModulesByComponent($component): array
    {
        $module_names = Database::prepare(
            "SELECT module_name" .
            " FROM `##module`" .
            " ORDER BY CASE :component WHEN 'menu' THEN menu_order WHEN 'sidebar' THEN sidebar_order WHEN 'tab' THEN tab_order ELSE 0 END, module_name"
        )->execute([
            'component' => $component,
        ])->fetchOneColumn();

        $array = [];
        foreach ($module_names as $module_name) {
            $interface = '\Fisharebest\Webtrees\Module\Module' . ucfirst($component) . 'Interface';
            $module    = self::getModuleByName($module_name);
            if ($module instanceof $interface) {
                $array[$module_name] = $module;
            }
        }

        // The order of menus/sidebars/tabs is defined in the database. Others are sorted by name.
        if ($component !== 'menu' && $component !== 'sidebar' && $component !== 'tab') {
            uasort($array, function (ModuleInterface $x, ModuleInterface $y): int {
                return I18N::strcasecmp($x->getTitle(), $y->getTitle());
            });
        }

        return $array;
    }

    /**
     * Get a list of modules which (a) provide a block and (b) we have permission to see.
     *
     * @param Tree $tree
     *
     * @return ModuleBlockInterface[]
     */
    public static function getActiveBlocks(Tree $tree): array
    {
        return self::getActiveModulesByComponent($tree, 'block');
    }

    /**
     * Get a list of modules which (a) provide a chart and (b) we have permission to see.
     *
     * @param Tree $tree
     *
     * @return ModuleChartInterface[]
     */
    public static function getActiveCharts(Tree $tree): array
    {
        return self::getActiveModulesByComponent($tree, 'chart');
    }

    /**
     * Get a list of modules which (a) provide a chart and (b) we have permission to see.
     *
     * @param Tree   $tree
     * @param string $module
     *
     * @return bool
     */
    public static function isActiveChart(Tree $tree, $module): bool
    {
        return array_key_exists($module, self::getActiveModulesByComponent($tree, 'chart'));
    }

    /**
     * Get a list of module names which have configuration options.
     *
     * @return ModuleConfigInterface[]
     */
    public static function configurableModules(): array
    {
        $modules = array_filter(self::getInstalledModules('disabled'), function (ModuleInterface $module): bool {
            return $module instanceof ModuleConfigInterface;
        });

        // Exclude disabled modules
        $enabled_modules = Database::prepare("SELECT module_name, status FROM `##module` WHERE status='enabled'")->fetchOneColumn();

        return array_filter($modules, function (ModuleConfigInterface $module) use ($enabled_modules): bool {
            return in_array($module->getName(), $enabled_modules);
        });
    }

    /**
     * Get a list of modules which (a) provide a menu and (b) we have permission to see.
     *
     * @param Tree $tree
     *
     * @return ModuleMenuInterface[]
     */
    public static function getActiveMenus(Tree $tree): array
    {
        return self::getActiveModulesByComponent($tree, 'menu');
    }

    /**
     * Get a list of modules which (a) provide a report and (b) we have permission to see.
     *
     * @param Tree $tree
     *
     * @return ModuleReportInterface[]
     */
    public static function getActiveReports(Tree $tree): array
    {
        return self::getActiveModulesByComponent($tree, 'report');
    }

    /**
     * Get a list of modules which (a) provide a sidebar and (b) we have permission to see.
     *
     * @param Tree $tree
     *
     * @return ModuleSidebarInterface[]
     */
    public static function getActiveSidebars(Tree $tree): array
    {
        return self::getActiveModulesByComponent($tree, 'sidebar');
    }

    /**
     * Get a list of modules which (a) provide a tab and (b) we have permission to see.
     *
     * @param Tree $tree
     *
     * @return ModuleTabInterface[]
     */
    public static function getActiveTabs(Tree $tree): array
    {
        return self::getActiveModulesByComponent($tree, 'tab');
    }

    /**
     * Get a list of modules which (a) provide a theme and (b) we have permission to see.
     *
     * @param Tree $tree
     *
     * @return ModuleThemeInterface[]
     */
    public static function getActiveThemes(Tree $tree): array
    {
        return self::getActiveModulesByComponent($tree, 'theme');
    }

    /**
     * Find a specified module, if it is currently active.
     *
     * @param string $module_name
     *
     * @return ModuleInterface|null
     */
    public static function getModuleByName($module_name)
    {
        $modules = self::getActiveModules();
        if (array_key_exists($module_name, $modules)) {
            return $modules[$module_name];
        }

        return null;
    }

    /**
     * Scan the source code to find a list of all installed modules.
     *
     * During setup, new modules need a status of “enabled”.
     * In admin->modules, new modules need status of “disabled”.
     *
     * @param string $default_status
     *
     * @return ModuleInterface[]
     */
    public static function getInstalledModules($default_status): array
    {
        $modules = [];

        foreach (glob(WT_ROOT . WT_MODULES_DIR . '*/module.php') as $file) {
            try {
                $module = self::loadModule($file);
                if ($module instanceof ModuleInterface) {
                    $modules[$module->getName()] = $module;
                    Database::prepare("INSERT IGNORE INTO `##module` (module_name, status, menu_order, sidebar_order, tab_order) VALUES (?, ?, ?, ?, ?)")->execute([
                        $module->getName(),
                        $default_status,
                        $module instanceof ModuleMenuInterface ? $module->defaultMenuOrder() : null,
                        $module instanceof ModuleSidebarInterface ? $module->defaultSidebarOrder() : null,
                        $module instanceof ModuleTabInterface ? $module->defaultTabOrder() : null,
                    ]);
                    // Set the default privcy for this module. Note that this also sets it for the
                    // default family tree, with a gedcom_id of -1
                    if ($module instanceof ModuleMenuInterface) {
                        Database::prepare(
                            "INSERT IGNORE INTO `##module_privacy` (module_name, gedcom_id, component, access_level)" .
                            " SELECT ?, gedcom_id, 'menu', ?" .
                            " FROM `##gedcom`"
                        )->execute([
                            $module->getName(),
                            $module->defaultAccessLevel(),
                        ]);
                    }
                    if ($module instanceof ModuleSidebarInterface) {
                        Database::prepare(
                            "INSERT IGNORE INTO `##module_privacy` (module_name, gedcom_id, component, access_level)" .
                            " SELECT ?, gedcom_id, 'sidebar', ?" .
                            " FROM `##gedcom`"
                        )->execute([
                            $module->getName(),
                            $module->defaultAccessLevel(),
                        ]);
                    }
                    if ($module instanceof ModuleTabInterface) {
                        Database::prepare(
                            "INSERT IGNORE INTO `##module_privacy` (module_name, gedcom_id, component, access_level)" .
                            " SELECT ?, gedcom_id, 'tab', ?" .
                            " FROM `##gedcom`"
                        )->execute([
                            $module->getName(),
                            $module->defaultAccessLevel(),
                        ]);
                    }
                    if ($module instanceof ModuleBlockInterface) {
                        Database::prepare(
                            "INSERT IGNORE INTO `##module_privacy` (module_name, gedcom_id, component, access_level)" .
                            " SELECT ?, gedcom_id, 'block', ?" .
                            " FROM `##gedcom`"
                        )->execute([
                            $module->getName(),
                            $module->defaultAccessLevel(),
                        ]);
                    }
                    if ($module instanceof ModuleChartInterface) {
                        Database::prepare(
                            "INSERT IGNORE INTO `##module_privacy` (module_name, gedcom_id, component, access_level)" .
                            " SELECT ?, gedcom_id, 'chart', ?" .
                            " FROM `##gedcom`"
                        )->execute([
                            $module->getName(),
                            $module->defaultAccessLevel(),
                        ]);
                    }
                    if ($module instanceof ModuleReportInterface) {
                        Database::prepare(
                            "INSERT IGNORE INTO `##module_privacy` (module_name, gedcom_id, component, access_level)" .
                            " SELECT ?, gedcom_id, 'report', ?" .
                            " FROM `##gedcom`"
                        )->execute([
                            $module->getName(),
                            $module->defaultAccessLevel(),
                        ]);
                    }
                    if ($module instanceof ModuleThemeInterface) {
                        Database::prepare(
                            "INSERT IGNORE INTO `##module_privacy` (module_name, gedcom_id, component, access_level)" .
                            " SELECT ?, gedcom_id, 'theme', ?" .
                            " FROM `##gedcom`"
                        )->execute([
                            $module->getName(),
                            $module->defaultAccessLevel(),
                        ]);
                    }
                }
            } catch (\Exception $ex) {
                DebugBar::addThrowable($ex);

                // Old or invalid module?
                Log::addErrorLog($ex->getMessage());
            }
        }

        return $modules;
    }

    /**
     * After creating a new family tree, we need to assign the default access
     * rights for each module.
     *
     * @param int $tree_id
     *
     * @return void
     */
    public static function setDefaultAccess($tree_id)
    {
        foreach (self::getInstalledModules('disabled') as $module) {
            if ($module instanceof ModuleMenuInterface) {
                Database::prepare(
                    "INSERT IGNORE `##module_privacy` (module_name, gedcom_id, component, access_level) VALUES (?, ?, 'menu', ?)"
                )->execute([
                    $module->getName(),
                    $tree_id,
                    $module->defaultAccessLevel(),
                ]);
            }
            if ($module instanceof ModuleSidebarInterface) {
                Database::prepare(
                    "INSERT IGNORE `##module_privacy` (module_name, gedcom_id, component, access_level) VALUES (?, ?, 'sidebar', ?)"
                )->execute([
                    $module->getName(),
                    $tree_id,
                    $module->defaultAccessLevel(),
                ]);
            }
            if ($module instanceof ModuleTabInterface) {
                Database::prepare(
                    "INSERT IGNORE `##module_privacy` (module_name, gedcom_id, component, access_level) VALUES (?, ?, 'tab', ?)"
                )->execute([
                    $module->getName(),
                    $tree_id,
                    $module->defaultAccessLevel(),
                ]);
            }
            if ($module instanceof ModuleBlockInterface) {
                Database::prepare(
                    "INSERT IGNORE `##module_privacy` (module_name, gedcom_id, component, access_level) VALUES (?, ?, 'block', ?)"
                )->execute([
                    $module->getName(),
                    $tree_id,
                    $module->defaultAccessLevel(),
                ]);
            }
            if ($module instanceof ModuleChartInterface) {
                Database::prepare(
                    "INSERT IGNORE `##module_privacy` (module_name, gedcom_id, component, access_level) VALUES (?, ?, 'chart', ?)"
                )->execute([
                    $module->getName(),
                    $tree_id,
                    $module->defaultAccessLevel(),
                ]);
            }
            if ($module instanceof ModuleReportInterface) {
                Database::prepare(
                    "INSERT IGNORE `##module_privacy` (module_name, gedcom_id, component, access_level) VALUES (?, ?, 'report', ?)"
                )->execute([
                    $module->getName(),
                    $tree_id,
                    $module->defaultAccessLevel(),
                ]);
            }
            if ($module instanceof ModuleThemeInterface) {
                Database::prepare(
                    "INSERT IGNORE `##module_privacy` (module_name, gedcom_id, component, access_level) VALUES (?, ?, 'theme', ?)"
                )->execute([
                    $module->getName(),
                    $tree_id,
                    $module->defaultAccessLevel(),
                ]);
            }
        }
    }
}
