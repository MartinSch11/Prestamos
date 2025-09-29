<?php
// tests/Feature/EquipoDisponibilidadTest.php
namespace Tests\Feature;

use App\Models\Equipo;
use App\Models\Reserva;
use App\Models\ReservaItem;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipoDisponibilidadTest extends TestCase
{
    use RefreshDatabase;

    /** Helper para crear reserva + item en un equipo */
    private function reservar(Equipo $equipo, string $ini, string $fin, int $cant, string $estado = 'pendiente'): void
    {
        $reserva = Reserva::factory()->create([
            'inicio' => Carbon::parse($ini),
            'fin' => Carbon::parse($fin),
            'estado' => $estado,
        ]);

        ReservaItem::factory()->create([
            'reserva_id' => $reserva->id,
            'equipo_id' => $equipo->id,
            'cantidad' => $cant,
        ]);
    }

    public function test_disponibilidad_total_sin_reservas(): void
    {
        $equipo = Equipo::factory()->create(['cantidad' => 5]);

        $disp = $equipo->disponibleEnRango(
            Carbon::parse('2025-09-30 10:00'),
            Carbon::parse('2025-09-30 12:00')
        );

        $this->assertSame(5, $disp);
    }

    public function test_descuenta_reservas_que_se_solapan(): void
    {
        $equipo = Equipo::factory()->create(['cantidad' => 5]);

        // Reserva solapada con el rango (11-13) vs (10-12) => se solapan
        $this->reservar($equipo, '2025-09-30 11:00', '2025-09-30 13:00', 2, 'pendiente');

        $disp = $equipo->disponibleEnRango(
            Carbon::parse('2025-09-30 10:00'),
            Carbon::parse('2025-09-30 12:00')
        );

        $this->assertSame(3, $disp);
    }

    public function test_no_descuenta_si_termina_exactamente_en_el_inicio(): void
    {
        $equipo = Equipo::factory()->create(['cantidad' => 5]);

        // (08-10) y consulto (10-12). No se solapan por borde (fin == inicio)
        $this->reservar($equipo, '2025-09-30 08:00', '2025-09-30 10:00', 4, 'pendiente');

        $disp = $equipo->disponibleEnRango(
            Carbon::parse('2025-09-30 10:00'),
            Carbon::parse('2025-09-30 12:00')
        );

        $this->assertSame(5, $disp);
    }

    public function test_no_descuenta_reservas_devueltas(): void
    {
        $equipo = Equipo::factory()->create(['cantidad' => 5]);

        // Misma franja, pero estado 'devuelto' -> no cuenta
        $this->reservar($equipo, '2025-09-30 10:30', '2025-09-30 11:30', 5, 'devuelto');

        $disp = $equipo->disponibleEnRango(
            Carbon::parse('2025-09-30 10:00'),
            Carbon::parse('2025-09-30 12:00')
        );

        $this->assertSame(5, $disp);
    }

    public function test_suma_multiples_solapes(): void
    {
        $equipo = Equipo::factory()->create(['cantidad' => 10]);

        // Tres reservas que solapan al menos parcialmente con (10-14)
        $this->reservar($equipo, '2025-09-30 09:00', '2025-09-30 11:00', 3, 'en_curso');   // solapa 10-11
        $this->reservar($equipo, '2025-09-30 10:30', '2025-09-30 12:30', 2, 'pendiente'); // solapa 10:30-12:00
        $this->reservar($equipo, '2025-09-30 13:00', '2025-09-30 15:00', 4, 'pendiente'); // solapa 13:00-14:00

        $disp = $equipo->disponibleEnRango(
            Carbon::parse('2025-09-30 10:00'),
            Carbon::parse('2025-09-30 14:00')
        );

        // Reservado total que solapa = 3 + 2 + 4 = 9 → disponible = 10 - 9 = 1
        $this->assertSame(1, $disp);
    }

    public function test_reserva_cubre_completamente_el_rango(): void
    {
        $equipo = Equipo::factory()->create(['cantidad' => 5]);

        $this->reservar($equipo, '2025-09-30 09:00', '2025-09-30 15:00', 3, 'pendiente');

        $disp = $equipo->disponibleEnRango(
            Carbon::parse('2025-09-30 10:00'),
            Carbon::parse('2025-09-30 12:00')
        );

        $this->assertSame(2, $disp);
    }

    public function test_reserva_contenida_completamente_en_el_rango(): void
    {
        $equipo = Equipo::factory()->create(['cantidad' => 5]);

        $this->reservar($equipo, '2025-09-30 11:00', '2025-09-30 11:30', 4, 'pendiente');

        $disp = $equipo->disponibleEnRango(
            Carbon::parse('2025-09-30 10:00'),
            Carbon::parse('2025-09-30 12:00')
        );

        $this->assertSame(1, $disp);
    }

    public function test_reserva_fuera_del_rango_sin_solape(): void
    {
        $equipo = Equipo::factory()->create(['cantidad' => 5]);

        $this->reservar($equipo, '2025-09-30 12:00', '2025-09-30 13:00', 5, 'pendiente');

        $disp = $equipo->disponibleEnRango(
            Carbon::parse('2025-09-30 10:00'),
            Carbon::parse('2025-09-30 12:00')
        );

        $this->assertSame(5, $disp);
    }

}
