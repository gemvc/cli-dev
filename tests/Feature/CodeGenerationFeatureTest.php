<?php

declare(strict_types=1);

namespace Gemvc\CliDev\Tests\Feature;

use Gemvc\CliDev\Tests\Support\FeatureTestCase;

final class CodeGenerationFeatureTest extends FeatureTestCase
{
    public function testCreateServiceGeneratesApiClassFromProjectTemplate(): void
    {
        $result = $this->runner->run('create:service', ['Product']);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('SUCCESS: Service Product created successfully', $result->output);

        $path = $this->projectRoot . '/app/api/Product.php';
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);
        $this->assertStringContainsString('class Product extends ApiService', $content);
        $this->assertStringNotContainsString('{$serviceName}', $content);
    }

    public function testCreateServiceWithFlagsBuildsFullStack(): void
    {
        $result = $this->runner->run('create:service', ['Order', '-cmt']);

        $this->assertTrue($result->success);
        $this->assertFileExists($this->projectRoot . '/app/api/Order.php');
        $this->assertFileExists($this->projectRoot . '/app/controller/OrderController.php');
        $this->assertFileExists($this->projectRoot . '/app/model/OrderModel.php');
        $this->assertFileExists($this->projectRoot . '/app/table/OrderTable.php');
    }

    public function testCreateCrudBuildsFullStack(): void
    {
        $result = $this->runner->run('create:crud', ['Blog']);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('SUCCESS: CRUD for Blog created successfully', $result->output);
        $this->assertFileExists($this->projectRoot . '/app/api/Blog.php');
        $this->assertFileExists($this->projectRoot . '/app/controller/BlogController.php');
        $this->assertFileExists($this->projectRoot . '/app/model/BlogModel.php');
        $this->assertFileExists($this->projectRoot . '/app/table/BlogTable.php');
    }

    public function testCreateControllerWithModelAndTableFlags(): void
    {
        $result = $this->runner->run('create:controller', ['Invoice', '-mt']);

        $this->assertTrue($result->success);
        $this->assertFileExists($this->projectRoot . '/app/controller/InvoiceController.php');
        $this->assertFileExists($this->projectRoot . '/app/model/InvoiceModel.php');
        $this->assertFileExists($this->projectRoot . '/app/table/InvoiceTable.php');
        $this->assertFileDoesNotExist($this->projectRoot . '/app/api/Invoice.php');
    }

    public function testCreateModelWithTableFlag(): void
    {
        $result = $this->runner->run('create:model', ['Customer', '-t']);

        $this->assertTrue($result->success);
        $this->assertFileExists($this->projectRoot . '/app/model/CustomerModel.php');
        $this->assertFileExists($this->projectRoot . '/app/table/CustomerTable.php');
    }

    public function testCreateTableOnly(): void
    {
        $result = $this->runner->run('create:table', ['Shipment']);

        $this->assertTrue($result->success);
        $this->assertFileExists($this->projectRoot . '/app/table/ShipmentTable.php');
        $content = (string) file_get_contents($this->projectRoot . '/app/table/ShipmentTable.php');
        $this->assertStringContainsString('class ShipmentTable', $content);
    }

    public function testServiceNameIsNormalized(): void
    {
        $result = $this->runner->run('create:service', ['mywidget']);

        $this->assertTrue($result->success);
        $this->assertFileExists($this->projectRoot . '/app/api/Mywidget.php');
    }

    public function testCreateServiceRequiresName(): void
    {
        $result = $this->runner->run('create:service', []);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Service name is required', $result->output);
    }

    public function testCreateControllerRequiresName(): void
    {
        $result = $this->runner->run('create:controller', []);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Controller name is required', $result->output);
    }

    public function testUsesPackageTemplatesWhenProjectTemplatesMissing(): void
    {
        $this->removeDirectory($this->projectRoot . '/templates');

        $result = $this->runner->run('create:service', ['Vendor']);

        $this->assertTrue($result->success);
        $path = $this->projectRoot . '/app/api/Vendor.php';
        $this->assertFileExists($path);
        $this->assertStringContainsString('class Vendor extends ApiService', (string) file_get_contents($path));
        $this->assertStringNotContainsString('{$serviceName}', (string) file_get_contents($path));
    }
}
