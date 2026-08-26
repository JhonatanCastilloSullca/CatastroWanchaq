@extends('layout.master')

@push('plugin-styles')
    <link
        href="{{ asset('assets/plugins/select2/select2.min.css') }}"
        rel="stylesheet"
    />

    <style>
        .contenedor-reportes {
            padding: 10px;
        }

        .titulo-reportes {
            color: #292929;
            font-weight: 600;
        }

        .descripcion-reportes {
            color: #6c757d;
            margin-bottom: 25px;
        }

        .botones-reportes {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 25px;
        }

        .boton-reporte {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 220px;
            min-height: 55px;
            margin: 0;
            padding: 10px 22px;
            border: 2px solid #292929;
            border-radius: 25px;
            background-color: #ffffff;
            color: #292929;
            font-size: 16px;
            font-weight: 600;
            text-align: center;
            transition: all 0.2s ease-in-out;
        }

        .boton-reporte:hover,
        .boton-reporte:focus {
            background-color: #292929;
            border-color: #292929;
            color: #ffffff;
        }

        .boton-excel {
            border-color: #198754;
            color: #198754;
        }

        .boton-excel:hover,
        .boton-excel:focus {
            background-color: #198754;
            border-color: #198754;
            color: #ffffff;
        }

        .boton-reporte i {
            margin-right: 8px;
            font-size: 21px;
        }

        .select2-container {
            width: 100% !important;
        }

        @media (max-width: 767px) {
            .botones-reportes {
                display: block;
            }

            .boton-reporte {
                width: 100%;
                margin-bottom: 15px;
            }
        }
    </style>
@endpush

@section('content')
    <div class="row">
        <div class="col-md-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <div class="contenedor-reportes">

                        {{-- Mensaje de éxito --}}
                        @if (session('success'))
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                {{ session('success') }}

                                <button
                                    type="button"
                                    class="btn-close"
                                    data-bs-dismiss="alert"
                                    aria-label="Cerrar"
                                ></button>
                            </div>
                        @endif

                        {{-- Mensaje de advertencia --}}
                        @if (session('warning'))
                            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                {{ session('warning') }}

                                <button
                                    type="button"
                                    class="btn-close"
                                    data-bs-dismiss="alert"
                                    aria-label="Cerrar"
                                ></button>
                            </div>
                        @endif

                        {{-- Errores de validación --}}
                        @if ($errors->any())
                            <div class="alert alert-danger" role="alert">
                                <strong>Se encontraron los siguientes errores:</strong>

                                <ul class="mb-0 mt-2">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <h4 class="mb-2 titulo-reportes">
                            Exportar información por sector
                        </h4>

                        <p class="descripcion-reportes">
                            Seleccione un sector y luego el tipo de información
                            que desea exportar.
                        </p>

                        <form
                            method="GET"
                            class="form-horizontal"
                            id="formReportesSector"
                        >
                            <div class="row form-group">
                                <div class="mb-3 col-md-6 col-lg-4">
                                    <label for="sector" class="form-label">
                                        Sector:
                                    </label>

                                    <select
                                        class="form-select @error('sector') is-invalid @enderror"
                                        name="sector"
                                        id="sector"
                                        required
                                    >
                                        <option value="">
                                            SELECCIONE UN SECTOR
                                        </option>

                                        @foreach ($sectores as $sector)
                                            <option
                                                value="{{ trim($sector->codi_sector) }}"
                                                {{ old('sector', request('sector')) == trim($sector->codi_sector) ? 'selected' : '' }}
                                            >
                                                {{ trim($sector->codi_sector) }}
                                            </option>
                                        @endforeach
                                    </select>

                                    @error('sector')
                                        <span class="invalid-feedback d-block">
                                            {{ $message }}
                                        </span>
                                    @enderror
                                </div>

                                <div class="col-md-12">
                                    <div class="botones-reportes">

                                        {{-- Reporte de fichas individuales --}}
                                        <button
                                            type="submit"
                                            class="btn boton-reporte"
                                            formaction="{{ route('reportes-sector.individuales') }}"
                                        >
                                            <i class="mdi mdi-account"></i>
                                            INDIVIDUALES
                                        </button>

                                        {{-- Reporte de fichas económicas --}}
                                        <button
                                            type="submit"
                                            class="btn boton-reporte"
                                            formaction="{{ route('reportes-sector.economicas') }}"
                                        >
                                            <i class="mdi mdi-store"></i>
                                            ECONÓMICAS
                                        </button>

                                        {{-- Reporte de bienes comunes --}}
                                        <button
                                            type="submit"
                                            class="btn boton-reporte"
                                            formaction="{{ route('reportes-sector.bien-comun') }}"
                                        >
                                            <i class="mdi mdi-home-group"></i>
                                            BIEN COMÚN
                                        </button>

                                        {{-- Reporte de puertas --}}
                                        <button
                                            type="submit"
                                            class="btn boton-reporte"
                                            formaction="{{ route('reportes-sector.puertas') }}"
                                        >
                                            <i class="mdi mdi-door"></i>
                                            PUERTAS
                                        </button>

                                        {{-- Cantidad de fichas por manzana --}}
                                        <button
                                            type="submit"
                                            class="btn boton-reporte boton-excel"
                                            formaction="{{ route('reportes.cantidad-fichas-exportar') }}"
                                        >
                                            <i class="mdi mdi-file-excel"></i>
                                            CANTIDAD DE FICHAS
                                        </button>

                                    </div>
                                </div>
                            </div>
                        </form>

                        <div class="alert alert-light border mt-4 mb-0">
                            <i class="mdi mdi-information-outline"></i>

                            El reporte <strong>Cantidad de fichas</strong>
                            contiene el resumen por manzana de fichas individuales,
                            cotitulares, económicas, bienes comunes y bienes culturales.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('plugin-scripts')
    <script src="{{ asset('assets/plugins/select2/select2.min.js') }}"></script>
@endpush

@push('custom-scripts')
    <script>
        $(document).ready(function () {
            const sector = $('#sector');

            sector.select2({
                width: '100%',
                placeholder: 'SELECCIONE UN SECTOR',
                allowClear: true
            });

            $('#formReportesSector').on('submit', function (event) {
                if (!sector.val()) {
                    event.preventDefault();

                    sector.select2('open');
                }
            });
        });
    </script>
@endpush