<?php


$employees = [
    ['name' => 'John', 'city' => 'Dallas'],
    ['name' => 'Jane', 'city' => 'Austin'],
    ['name' => 'Jake', 'city' => 'Dallas'],
    ['name' => 'Jill', 'city' => 'Dallas'],
];

$offices = [
    ['office' => 'Dallas HQ', 'city' => 'Dallas'],
    ['office' => 'Dallas South', 'city' => 'Dallas'],
    ['office' => 'Austin Branch', 'city' => 'Austin'],
];

// $output = [
//     "Dallas" => [
//         "Dallas HQ" => ["John", "Jake", "Jill"],
//         "Dallas South" => ["John", "Jake", "Jill"],
//     ],
//     "Austin" => [
//         "Austin Branch" => ["Jane"],
//     ],
// ];

// Get all employees for a city
$employeesByCity = [];
foreach ($employees as $employee) {
    $city = $employee['city'];
    if (!isset($employeesByCity[$city])) {
        $employeesByCity[$city] = [];
    }
    $employeesByCity[$city][] = $employee['name'];
}

$output = [];

// Build the office-epmoyee mapping
foreach ($offices as $office) {
    $city = $office['city'];
    $officeName = $office['office'];

    // Create city key if it doesn't exist
    if (!isset($output[$city])) {
        $output[$city] = [];
    }

    // Assign employees to an office
    $output[$city][$officeName] = $employeesByCity[$city] ?? [];
}

// Print the output
echo json_encode($output);
