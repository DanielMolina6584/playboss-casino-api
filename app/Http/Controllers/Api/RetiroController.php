<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsJson;
use App\Http\Requests\ListadoRequest;
use App\Http\Requests\RetiroRequest;
use App\Models\Payment\Retiro;
use App\Service\Auth\Mfa\SvcMfa;
use App\Service\Payment\RetiroService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class RetiroController extends Controller
{
    use RespondsJson;

    public function __construct(private readonly RetiroService $retiros, private readonly SvcMfa $mfa)
    {
    }

    public function store(RetiroRequest $r): JsonResponse
    {
        // Con 2FA activo, cada solicitud de retiro exige un código vigente (TOTP o de recuperación).
        $this->mfa->exigirParaRetiro($r->user(), $r->input('codigo_mfa'), $r->input('codigo_recuperacion'), $r->ip(), $r->userAgent());

        $retiro = $this->retiros->solicitar(
            (int) $r->user()->getAuthIdentifier(),
            (string) $r->input('monto'),
            $r->only(['banco', 'tipo_cuenta', 'numero_cuenta', 'titular', 'documento_titular']),
            (string) $r->input('idempotency_key')
        );

        return $this->ok(self::presentar($retiro), $retiro->wasRecentlyCreated ? 201 : 200, ['Solicitud de retiro recibida; será revisada por un administrador.']);
    }

    public function index(ListadoRequest $r): JsonResponse
    {
        $q = Retiro::query()->where('usuario_id', $r->user()->getAuthIdentifier())->orderByDesc('solicitado_en')->orderByDesc('id_retiro');
        $r->filled('estado') && $q->where('estado', $r->string('estado'));
        $r->filled('desde') && $q->where('solicitado_en', '>=', $r->date('desde')->startOfDay());
        $r->filled('hasta') && $q->where('solicitado_en', '<=', $r->date('hasta')->endOfDay());

        return $this->paginated($q->paginate($r->porPagina()), fn (Retiro $x) => self::presentar($x));
    }

    /** Vista del cliente (cuenta enmascarada) o del administrador (completa, con usuario). */
    public static function presentar(Retiro $x, bool $admin = false): array
    {
        $numero = (string) $x->numero_cuenta;

        return [
            'id' => $x->id_retiro,
            'estado' => $x->estado,
            'monto' => $x->monto,
            'moneda' => $x->moneda,
            'destino' => [
                'banco' => $x->banco,
                'tipo_cuenta' => $x->tipo_cuenta,
                'numero_cuenta' => $admin ? $numero : '****' . substr($numero, -4),
                'titular' => $x->titular,
            ] + ($admin ? ['documento_titular' => $x->documento_titular] : []),
            'motivo_rechazo' => $x->motivo_rechazo,
            'solicitado_en' => $x->solicitado_en,
            'resuelto_en' => $x->resuelto_en,
        ] + ($admin ? ['usuario_id' => $x->usuario_id, 'admin_id' => $x->admin_id] : []);
    }
}
