<?php

namespace Webkul\Manufacturing;

use Filament\Panel;
use Webkul\PluginManager\Console\Commands\InstallCommand;
use Webkul\PluginManager\Console\Commands\UninstallCommand;
use Webkul\PluginManager\Package;
use Webkul\PluginManager\PackageServiceProvider;

class ManufacturingServiceProvider extends PackageServiceProvider
{
    public static string $name = 'manufacturing';

    public static string $viewNamespace = 'manufacturing';

    public function configureCustomPackage(Package $package): void
    {
        $package->name(static::$name)
            ->hasViews()
            ->hasTranslations()
            ->hasMigrations([
                '2026_03_31_064242_create_manufacturing_bills_of_materials_table',
                '2026_03_31_064243_create_manufacturing_work_centers_table',
                '2026_03_31_064244_create_manufacturing_operations_table',
                '2026_03_31_064245_create_manufacturing_bill_of_material_lines_table',
                '2026_03_31_064246_create_manufacturing_bill_of_material_byproducts_table',
                '2026_03_31_064247_create_manufacturing_orders_table',
                '2026_03_31_064248_create_manufacturing_work_orders_table',
                '2026_03_31_064249_create_manufacturing_unbuild_orders_table',
            ])
            ->runsMigrations()
            ->hasDependencies([
                'products',
                'inventories',
            ])
            ->hasInstallCommand(function (InstallCommand $command): void {
                $command
                    ->installDependencies()
                    ->runsMigrations();
            })
            ->hasUninstallCommand(function (UninstallCommand $command): void {})
            ->icon('manufacturing');
    }

    public function packageBooted(): void
    {
        //
    }

    public function packageRegistered(): void
    {
        Panel::configureUsing(function (Panel $panel): void {
            $panel->plugin(ManufacturingPlugin::make());
        });
    }
}
