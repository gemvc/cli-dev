<?php

declare(strict_types=1);

namespace Gemvc\CliDev\Tests\Support\Commands;

use Gemvc\CLI\Commands\AdminSetpassword;
use Gemvc\CLI\Commands\CreateController;
use Gemvc\CLI\Commands\CreateCrud;
use Gemvc\CLI\Commands\CreateModel;
use Gemvc\CLI\Commands\CreateService;
use Gemvc\CLI\Commands\CreateTable;
use Gemvc\CLI\Commands\DbDescribe;
use Gemvc\CLI\Commands\DbDrop;
use Gemvc\CLI\Commands\DbInit;
use Gemvc\CLI\Commands\DbMigrate;
use Gemvc\CLI\Commands\DbList;
use Gemvc\CLI\Commands\DbUnique;
use Gemvc\CLI\Commands\SetAdmin;
use Gemvc\CliDev\Tests\Support\SuppressesCliExit;

final class TestableCreateService extends CreateService
{
    use SuppressesCliExit;
}

final class TestableCreateController extends CreateController
{
    use SuppressesCliExit;
}

final class TestableCreateModel extends CreateModel
{
    use SuppressesCliExit;
}

final class TestableCreateTable extends CreateTable
{
    use SuppressesCliExit;
}

final class TestableCreateCrud extends CreateCrud
{
    use SuppressesCliExit;

    protected function newCreateService(array $args, array $options): CreateService
    {
        return new TestableCreateService($args, $options);
    }
}

final class TestableCreateCrudBase extends CreateCrud
{
    use SuppressesCliExit;
}

final class TestableDbInit extends DbInit
{
    use SuppressesCliExit;
}

final class TestableDbMigrate extends DbMigrate
{
    use SuppressesCliExit;
}

final class TestableDbList extends DbList
{
    use SuppressesCliExit;
}

final class TestableDbDescribe extends DbDescribe
{
    use SuppressesCliExit;
}

final class TestableDbDrop extends DbDrop
{
    use SuppressesCliExit;
}

final class TestableDbUnique extends DbUnique
{
    use SuppressesCliExit;
}

final class TestableAdminSetpassword extends AdminSetpassword
{
    use SuppressesCliExit;
}

final class TestableSetAdmin extends SetAdmin
{
    use SuppressesCliExit;
}
