<?php
require "/var/www/pve-panel/vendor/autoload.php";
$app = require_once "/var/www/pve-panel/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== ORDERS ===\n";
$orders = DB::select("SELECT id,order_no,user_id,product_id,payment_status,vm_id,transaction_id,amount FROM orders ORDER BY id DESC LIMIT 5");
foreach ($orders as $o) {
    echo "  #{$o->id}: {$o->order_no} | status={$o->payment_status} | vm_id={$o->vm_id} | txn={$o->transaction_id} | amt={$o->amount}\n";
}

echo "\n=== VIRTUAL MACHINES ===\n";
$vms = DB::select("SELECT id,name,status,vm_id,order_id,node_id,cpu,memory,disk FROM virtual_machines ORDER BY id DESC LIMIT 5");
foreach ($vms as $v) {
    echo "  VM#{$v->id}: {$v->name} | status={$v->status} | vmid={$v->vm_id} | order_id={$v->order_id} | node={$v->node_id} | {$v->cpu}C/{$v->memory}M/{$v->disk}G\n";
}

echo "\n=== TRANSACTIONS ===\n";
$txns = DB::select("SELECT id,type,amount,transaction_id,reference_type,reference_id,created_at FROM transactions ORDER BY id DESC LIMIT 5");
foreach ($txns as $t) {
    echo "  Txn#{$t->id}: type={$t->type} | amt={$t->amount} | txn_id={$t->transaction_id} | ref={$t->reference_type}#{$t->reference_id}\n";
}
