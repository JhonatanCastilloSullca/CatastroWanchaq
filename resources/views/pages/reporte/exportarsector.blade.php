@extends('layout.master')

@push('plugin-styles')
    <link href="{{ asset('assets/plugins/select2/select2.min.css') }}" rel="stylesheet" />
@endpush

@section('content')
    <div class="row">
        <div class="col-md-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    @if (session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if (session('warning'))
                        <div class="alert alert-warning">
                            {{ session('warning') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            @foreach ($errors->all() as $error)
                                <div>{{ $error }}</div>
                            @endforeach
                        </div>
                    @endif
                    <div class="row">
                        <form method="GET" class="form-horizontal" id="formReportesSector">

                            <div class="row form-group">

                                <h4 class="mb-4">
                                    Exportar información por sector
                                </h4>

                                <div class="mb-3 col-md-4">
                                    <label for="sector" class="form-label">
                                        Sector:
                                    </label>

                                    <select
                                        class="form-select"
                                        data-width="100%"
                                        data-live-search="true"
                                        name="sector"
                                        id="sector"
                                        required
                                    >
                                        <option value="">
                                            SELECCIONE UN SECTOR
                                        </option>

                                        @foreach ($sectores as $sector)
                                            <option
                                                value="{{ $sector->codi_sector }}"
                                                {{ old('sector', request('sector')) == $sector->codi_sector ? 'selected' : '' }}
                                            >
                                                {{ $sector->codi_sector }}
                                            </option>
                                        @endforeach
                                    </select>

                                    @error('sector')
                                        <span class="error-message" style="color:red">
                                            {{ $message }}
                                        </span>
                                    @enderror
                                </div>

                                <div class="col-md-12 mt-3">

                                    <button
                                        type="submit"
                                        class="btn btn-outline-primary boton-reporte"
                                        formaction="{{ route('reportes-sector.individuales') }}"
                                    >
                                        INDIVIDUALES
                                    </button>

                                    <button
                                        type="submit"
                                        class="btn btn-outline-primary boton-reporte"
                                        formaction="{{ route('reportes-sector.economicas') }}"
                                    >
                                        ECONÓMICAS
                                    </button>

                                    <button
                                        type="submit"
                                        class="btn btn-outline-primary boton-reporte"
                                        formaction="{{ route('reportes-sector.bien-comun') }}"
                                    >
                                        BIEN COMÚN
                                    </button>

                                    <button
                                        type="submit"
                                        class="btn btn-outline-primary boton-reporte"
                                        formaction="{{ route('reportes-sector.puertas') }}"
                                    >
                                        PUERTAS
                                    </button>

                                </div>

                            </div>
                        </form>
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
        $(document).ready(function() {
            $('#sector').select2({
                width: '100%',
                placeholder: 'SELECCIONE UN SECTOR',
                allowClear: true
            });
        });
    </script>

    <style>
        .boton-reporte {
            min-width: 220px;
            min-height: 55px;
            margin-right: 15px;
            margin-bottom: 15px;
            border: 2px solid #292929;
            border-radius: 25px;
            background-color: #ffffff;
            color: #292929;
            font-size: 18px;
        }

        .boton-reporte:hover {
            background-color: #292929;
            border-color: #292929;
            color: #ffffff;
        }
    </style>
@endpush