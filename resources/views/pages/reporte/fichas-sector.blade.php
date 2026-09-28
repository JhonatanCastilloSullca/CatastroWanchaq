@extends('layout.master')

@section('content')
    <div class="row">
        <div class="col-md-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-4">Reporte de fichas por sector</h4>

                    <form method="GET" action="{{ route('reportes-sector.reporte') }}" class="row align-items-end mb-4">
                        <div class="col-md-4 mb-2">
                            <label for="sector" class="form-label">Sector</label>
                            <select name="sector" id="sector" class="form-select" required onchange="this.form.submit()">
                                <option value="">SELECCIONE UN SECTOR</option>
                                @foreach ($sectores as $opcion)
                                    <option value="{{ $opcion->id_sector }}" @selected($sector && $sector->id_sector === $opcion->id_sector)>
                                        {{ trim($opcion->codi_sector) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-auto mb-2">
                            <button type="submit" class="btn btn-primary">Consultar</button>
                        </div>
                    </form>

                    @if ($errors->any())
                        <div class="alert alert-danger">{{ $errors->first() }}</div>
                    @endif

                    @if ($sector)
                        <nav aria-label="Ubicación del reporte" class="mb-3">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item" @if (!$manzana) aria-current="page" @endif>
                                    @if ($manzana)
                                        <a href="{{ route('reportes-sector.reporte', ['sector' => $sector->id_sector]) }}">Sector {{ trim($sector->codi_sector) }}</a>
                                    @else
                                        Sector {{ trim($sector->codi_sector) }}
                                    @endif
                                </li>
                                @if ($manzana)
                                    <li class="breadcrumb-item" @if (!$lote) aria-current="page" @endif>
                                        @if ($lote)
                                            <a href="{{ route('reportes-sector.reporte', ['sector' => $sector->id_sector, 'manzana' => $manzana->id_mzna]) }}">Manzana {{ trim($manzana->codi_mzna) }}</a>
                                        @else
                                            Manzana {{ trim($manzana->codi_mzna) }}
                                        @endif
                                    </li>
                                @endif
                                @if ($lote)
                                    <li class="breadcrumb-item active" aria-current="page">Lote {{ trim($lote->codi_lote) }}</li>
                                @endif
                            </ol>
                        </nav>

                        @if (!$lote)
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover text-center">
                                    <thead>
                                        <tr>
                                            <th>{{ $manzana ? 'Lote' : 'MZ' }}</th>
                                            <th>UUCC</th>
                                            <th>Cotitulares</th>
                                            <th>Económicas</th>
                                            <th>Bien común</th>
                                            <th>Número de fichas</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($filas as $fila)
                                            <tr>
                                                <td>
                                                    <a class="d-block fw-bold" href="{{ route('reportes-sector.reporte', $manzana
                                                        ? ['sector' => $sector->id_sector, 'manzana' => $manzana->id_mzna, 'lote' => $fila->id]
                                                        : ['sector' => $sector->id_sector, 'manzana' => $fila->id]) }}">
                                                        {{ trim($fila->codigo) }}
                                                    </a>
                                                </td>
                                                @foreach ($tipos as $tipo)
                                                    <td>{{ number_format($fila->{$tipo['columna']}, 0, '.', ',') }}</td>
                                                @endforeach
                                                <td class="fw-bold">{{ number_format($fila->total, 0, '.', ',') }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="6">{{ $manzana ? 'No hay lotes en esta manzana.' : 'No hay manzanas en este sector.' }}</td></tr>
                                        @endforelse
                                    </tbody>
                                    <tfoot class="fw-bold">
                                        <tr>
                                            <td>TOTAL</td>
                                            @foreach ($tipos as $tipo)
                                                <td>{{ number_format($filas->sum($tipo['columna']), 0, '.', ',') }}</td>
                                            @endforeach
                                            <td>{{ number_format($filas->sum('total'), 0, '.', ',') }}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        @else
                            <div class="mb-3">Número de fichas: <strong>{{ number_format($fichas->total(), 0, '.', ',') }}</strong></div>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr><th>Número de ficha</th><th>Tipo de ficha</th><th>Código referencial catastral</th><th>Ficha</th></tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($fichas as $ficha)
                                            @php($tipo = $tipos[trim($ficha->tipo_ficha)])
                                            <tr>
                                                <td>{{ $ficha->nume_ficha }}</td>
                                                <td>{{ $tipo['nombre'] }}</td>
                                                <td>{{ $ficha->id_uni_cat }}</td>
                                                <td>
                                                    @can($tipo['ruta'])
                                                        <a href="{{ route($tipo['ruta'], ['ficha' => $ficha->id_ficha]) }}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm">Ver ficha</a>
                                                    @endcan
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="4">No hay fichas activas en este lote.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-3">{{ $fichas->links('pagination::bootstrap-4') }}</div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
