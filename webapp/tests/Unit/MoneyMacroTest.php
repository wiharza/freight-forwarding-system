<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MoneyMacroTest extends TestCase
{
    #[Test]
    public function money_macro_menghasilkan_kolom_decimal_presisi_18_4(): void
    {
        $connection = $this->app['db']->connection();
        $connection->useDefaultSchemaGrammar();
        $blueprint = new Blueprint($connection, 'money_macro_probe');

        $column = $blueprint->money('cif_rupiah');

        $this->assertSame('decimal', $column->type);
        $this->assertSame(18, $column['total']);
        $this->assertSame(4, $column['places']);
    }

    #[Test]
    public function money_macro_terdaftar_pada_blueprint(): void
    {
        $this->assertTrue(Blueprint::hasMacro('money'));
    }
}
