<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * RefreshDatabase drops every table, so refuse to run against anything
     * but a MySQL/MariaDB database whose name says it is for tests.
     */
    protected function setUpTraits()
    {
        $connection = config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if (! in_array(config("database.connections.{$connection}.driver"), ['mysql', 'mariadb'], true) || ! str_contains($database, 'test')) {
            throw new RuntimeException("Tests run on MySQL/MariaDB only, in a database named like \"mci_test\" (got {$connection}: {$database}).");
        }

        return parent::setUpTraits();
    }
}
