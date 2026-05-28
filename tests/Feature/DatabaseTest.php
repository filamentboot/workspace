<?php

/**
 * 数据库连接测试
 *
 * 验证测试数据库（MySQL）连接正常，
 * 可以正常读写数据。
 */
test('can create and query records in test database', function () {
    DB::table('migrations')->insert([
        'migration' => 'test_migration_verify',
        'batch'     => 999,
    ]);

    expect(DB::table('migrations')->where('migration', 'test_migration_verify')->exists())
        ->toBeTrue();
});
