<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Http\Kernel')->bootstrap();

$count = DB::connection('sales')->table('online_reports')->count();
echo "Total reports: $count\n";
$last = DB::connection('sales')->table('online_reports')->orderBy('id', 'desc')->first(['id', 'sales_note_ids']);
if ($last) {
    echo "Last report ID: " . $last->id . "\n";
    echo "Sales Note IDs: " . $last->sales_note_ids . "\n";
}
