<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('test')->plainTextToken;
});

function createMinimalXlsx(array $headers, array $rows = []): string
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();

    // Write headers
    foreach ($headers as $colIndex => $header) {
        $sheet->setCellValueByColumnAndRow($colIndex + 1, 1, $header);
    }

    // Write data rows
    foreach ($rows as $rowIndex => $row) {
        foreach ($row as $colIndex => $value) {
            $sheet->setCellValueByColumnAndRow($colIndex + 1, $rowIndex + 2, $value);
        }
    }

    $tempFile = tempnam(sys_get_temp_dir(), 'import_test_');
    $writer = new Xlsx($spreadsheet);
    $writer->save($tempFile);

    return $tempFile;
}

it('returns student template as xlsx', function () {
    $response = $this->withToken($this->token)
        ->getJson('/api/imports/template/students');

    $response->assertOk()
        ->assertHeader('Content-Disposition');
});

it('returns invoice template as xlsx', function () {
    $response = $this->withToken($this->token)
        ->getJson('/api/imports/template/tuition-invoices');

    $response->assertOk()
        ->assertHeader('Content-Disposition');
});

it('previews student import', function () {
    $path = createMinimalXlsx(
        ['nis', 'name', 'school_class', 'parent_name', 'parent_phone', 'parent_email', 'monthly_fee', 'status'],
        [
            ['1234567890', 'John Doe', 'X-A', 'Jane Doe', '081234567890', 'jane@example.com', 200000, 'active'],
        ]
    );

    $file = new UploadedFile($path, 'students.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    $response = $this->withToken($this->token)
        ->postJson('/api/imports/preview', [
            'file' => $file,
            'type' => 'students',
        ]);

    $response->assertOk()
        ->assertJsonStructure(['token', 'rows', 'summary'])
        ->assertJsonStructure(['summary' => ['total']]);

    @unlink($path);
});

it('rejects non-xlsx file', function () {
    $file = UploadedFile::fake()->createWithContent('test.txt', 'this is not an xlsx file');

    $this->withToken($this->token)
        ->postJson('/api/imports/preview', [
            'file' => $file,
            'type' => 'students',
        ])
        ->assertUnprocessable();
});
