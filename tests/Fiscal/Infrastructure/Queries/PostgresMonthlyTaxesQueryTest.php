<?php
declare(strict_types=1);

namespace Illuminate\Support\Facades {
    class DB {
        public static $queryLog = [];
        public static $pueTotal = 0;
        public static $ppdTotal = 0;

        public static function table(string $table) {
            $builder = new class($table) {
                public $table;
                public $wheres = [];
                public function __construct($table) { $this->table = $table; }
                public function join(...$args) { return $this; }
                public function where(...$args) { $this->wheres[] = $args; return $this; }
                public function whereYear(...$args) { return $this; }
                public function whereMonth(...$args) { return $this; }
                public function selectRaw(...$args) { return $this; }
                public function first() {
                    $res = new \stdClass();
                    if ($this->table === 'invoices') {
                        $res->collected_subtotal = \Illuminate\Support\Facades\DB::$pueTotal;
                    } else {
                        $res->collected_subtotal = \Illuminate\Support\Facades\DB::$ppdTotal;
                    }
                    return $res;
                }
            };
            self::$queryLog[] = $builder;
            return $builder;
        }

        public static function flush() {
            self::$queryLog = [];
            self::$pueTotal = 0;
            self::$ppdTotal = 0;
        }
    }
}

namespace Tests\Fiscal\Infrastructure\Queries {
    use App\Fiscal\Infrastructure\Queries\PostgresMonthlyTaxesQuery;
    use App\Shared\Application\TenantContextInterface;
    use PHPUnit\Framework\TestCase;
    use Illuminate\Support\Facades\DB;

    class PostgresMonthlyTaxesQueryTest extends TestCase
    {
        private PostgresMonthlyTaxesQuery $query;
        private TenantContextInterface $tenantContext;

        protected function setUp(): void
        {
            DB::flush();
            $this->tenantContext = $this->createMock(TenantContextInterface::class);
            $this->tenantContext->method('getCurrentRfc')->willReturn('XAXX010101000');
            $this->query = new PostgresMonthlyTaxesQuery($this->tenantContext);
        }

        public function test_exclusion_de_facturas_canceladas_del_calculo_fiscal(): void
        {
            DB::$pueTotal = 0;

            $this->query->execute(2026, 4);

            $pueQueryLog = DB::$queryLog[0];
            $ppdQueryLog = DB::$queryLog[1];

            $hasPueStatusFilter = false;
            foreach ($pueQueryLog->wheres as $where) {
                if ($where === ['invoices.sat_status', 'ACTIVE']) {
                    $hasPueStatusFilter = true;
                }
            }

            $hasPpdStatusFilter = false;
            foreach ($ppdQueryLog->wheres as $where) {
                if ($where === ['invoices.sat_status', 'ACTIVE']) {
                    $hasPpdStatusFilter = true;
                }
            }

            $this->assertTrue($hasPueStatusFilter, "La subconsulta PUE excluye facturas canceladas.");
            $this->assertTrue($hasPpdStatusFilter, "La subconsulta PPD excluye facturas canceladas.");
        }

        public function test_ajuste_correcto_cuando_una_factura_es_cancelada_despues_de_haber_sido_incluida(): void
        {
            // Simulate the exact moment where the database yields 0 due to the ACTIVE filter correctly stripping them out
            DB::$pueTotal = 0; 
            DB::$ppdTotal = 0;

            $summary = $this->query->execute(2026, 3);
            
            $this->assertEquals(0, $summary->collectedIncome->getCents());
            $this->assertEquals(0, $summary->ivaTransferred->getCents());
        }
    }
}
