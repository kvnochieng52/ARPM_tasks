<?php

namespace Tests\Unit\Services;

use App\Jobs\ProcessProductImage;
use App\Models\Product;
use App\Services\SpreadsheetService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use Mockery;

class SpreadsheetServiceTest extends TestCase
{
    private $spreadsheetService;
    private $mockImporter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->spreadsheetService = new SpreadsheetService();

        //a mock importer to be used in tests
        $this->mockImporter = Mockery::mock('importer');
        app()->instance('importer', $this->mockImporter);

        Queue::fake();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_processes_a_valid_spreadsheet_with_multiple_products()
    {
        // Arrange
        $mockData = [
            ['product_code' => 'PROD001', 'quantity' => 10],
            ['product_code' => 'PROD002', 'quantity' => 5],
            ['product_code' => 'PROD003', 'quantity' => 20],
        ];

        $this->mockImporter->shouldReceive('import')
            ->once()
            ->andReturn($mockData);

        // Act
        $this->spreadsheetService->processSpreadsheet('dummy_path.xlsx');

        // Assert
        $this->assertCount(3, Product::all());
        Queue::assertPushed(ProcessProductImage::class, 3);
    }

    /** @test */
    public function it_skips_rows_with_missing_product_code()
    {
        // Arrange
        $mockData = [
            ['product_code' => 'PROD001', 'quantity' => 10], // valid
            ['quantity' => 5], // invalid - missing product_code
            ['product_code' => 'PROD003', 'quantity' => 20], // valid
        ];

        $this->mockImporter->shouldReceive('import')
            ->once()
            ->andReturn($mockData);

        // Act
        $this->spreadsheetService->processSpreadsheet('dummy_path.xlsx');

        // Assert
        $this->assertCount(2, Product::all());
        Queue::assertPushed(ProcessProductImage::class, 2);
    }

    /** @test */
    public function it_skips_rows_with_duplicate_product_codes()
    {
        // Arrange
        $mockData = [
            ['product_code' => 'PROD001', 'quantity' => 10], // valid
            ['product_code' => 'PROD001', 'quantity' => 5], // duplicate
            ['product_code' => 'PROD002', 'quantity' => 20], // valid
        ];

        $this->mockImporter->shouldReceive('import')
            ->once()
            ->andReturn($mockData);

        // Create the first product to force a duplicate
        Product::create(['code' => 'PROD001', 'quantity' => 15]);

        // Act
        $this->spreadsheetService->processSpreadsheet('dummy_path.xlsx');

        // Assert
        $this->assertCount(2, Product::all()); // original + one new
        Queue::assertPushed(ProcessProductImage::class, 1); // only for PROD002
    }

    /** @test */
    public function it_skips_rows_with_invalid_quantity()
    {
        // Arrange
        $mockData = [
            ['product_code' => 'PROD001', 'quantity' => 10], // valid
            ['product_code' => 'PROD002', 'quantity' => 0], // invalid (min:1)
            ['product_code' => 'PROD003', 'quantity' => 'abc'], // invalid (not integer)
            ['product_code' => 'PROD004', 'quantity' => 5], // valid
        ];

        $this->mockImporter->shouldReceive('import')
            ->once()
            ->andReturn($mockData);

        // Act
        $this->spreadsheetService->processSpreadsheet('dummy_path.xlsx');

        // Assert
        $this->assertCount(2, Product::all());
        Queue::assertPushed(ProcessProductImage::class, 2);
    }

    /** @test */
    public function it_handles_empty_spreadsheet_gracefully()
    {
        // Arrange
        $this->mockImporter->shouldReceive('import')
            ->once()
            ->andReturn([]);

        // Act
        $this->spreadsheetService->processSpreadsheet('empty_file.xlsx');

        // Assert
        $this->assertCount(0, Product::all());
        Queue::assertNothingPushed();
    }

    /** @test */
    public function it_dispatches_image_processing_job_for_each_valid_product()
    {
        // Arrange
        $mockData = [
            ['product_code' => 'PROD001', 'quantity' => 10],
            ['product_code' => 'PROD002', 'quantity' => 5],
        ];

        $this->mockImporter->shouldReceive('import')
            ->once()
            ->andReturn($mockData);

        // Act
        $this->spreadsheetService->processSpreadsheet('dummy_path.xlsx');

        // Assert
        Queue::assertPushed(ProcessProductImage::class, 2);

        // Verify the jobs were dispatched with the correct products
        $products = Product::all();
        Queue::assertPushed(ProcessProductImage::class, function ($job) use ($products) {
            return $job->product->is($products->first()) || $job->product->is($products->last());
        });
    }
}
