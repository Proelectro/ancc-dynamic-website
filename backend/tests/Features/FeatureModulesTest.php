<?php

declare(strict_types=1);

namespace Ancc\Tests\Features;

use Ancc\App;
use Ancc\FeatureModule;
use PHPUnit\Framework\TestCase;

/** Feature folders are found automatically; the _Template folder is not. Put feature tests in this folder. */
final class FeatureModulesTest extends TestCase
{
    public function testModulesAreDiscoveredAndTemplateIsSkipped(): void
    {
        foreach (App::featureModules() as $module) {
            $this->assertInstanceOf(FeatureModule::class, $module);
            $this->assertStringNotContainsString('_Template', $module::class);
        }
        $this->assertTrue(class_exists(\Ancc\Features\_Template\Module::class), 'template should still autoload');
    }
}
