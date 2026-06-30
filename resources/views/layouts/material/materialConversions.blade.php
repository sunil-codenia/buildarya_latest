@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'Material Unit Conversion'])

    <div class="row clearfix">
        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="card project_list">
                <div class="header">
                    <h2><strong>Material Unit Conversion </strong> List&nbsp;<i class="zmdi zmdi-info info-hover"></i>
                        <div class="info-content">Material Unit Conversion will be listed here.</div>
                    </h2>
                    <div class="align-right">
                        @if (checkmodulepermission(3, 'can_add') == 1)
                            <button type="button" onclick="convertMaterial();"
                                class="btn btn-secondry btn-fill btn-round waves-effect"><a>Convert Material
                                    Unit</a></button>
                        @endif
                    </div>

                </div>
                @if (checkmodulepermission(3, 'can_view') == 1)
                    <div class="body">
                        <div id="consumption_view" class="table-responsive">

                            <table id="dataTable" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Date</th>
                                        <th>Site</th>
                                        <th>Material</th>
                                        <th>From Unit</th>
                                        <th>Quantity</th>
                                        <th>To Unit</th>
                                        <th>Updated Quantity</th>

                                        <th>User</th>
                                        <th>Remark</th>

                                        <th>Action</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    @php

                                        $i = 1;
                                    @endphp
                                    @foreach ($material_conversion as $dd)
                                        <tr>
                                            <td>{{ $i++ }}

                                            </td>

                                            <td>
                                                {{ $dd->date }}
                                            </td>
                                            <td>
                                                {{ $dd->site }}
                                            </td>
                                            <td>
                                                {{ $dd->material }}
                                            </td>
                                            <td>
                                                {{ $dd->f_unit }}
                                            </td>
                                            <td>
                                                {{ $dd->qty }}
                                            </td>
                                            <td>
                                                {{ $dd->t_unit }}
                                            </td>
                                            <td>
                                                {{ $dd->updated_qty }}
                                            </td>
                                            <td>
                                                {{ $dd->user }}
                                            </td>
                                            <td>
                                                {{ $dd->remark }}
                                            </td>

                                            <td class="text-center">


                                                @if (checkmodulepermission(3, 'can_delete') == 1)
                                                    <button title="Delete" type="button"
                                                        onclick="deletedata('{{ $dd->id }}')" style="all:unset"><i
                                                            class="zmdi zmdi-delete"></i> </button>
                                                @endif

                                            </td>
                                        </tr>
                                    @endforeach

                                </tbody>


                            </table>

                        </div>
                    </div>
                @else
                    <div class="alert alert-danger"> You Don't Have Permission to View !!</div>
                @endif
            </div>
        </div>
    </div>
    <script>
        function convertMaterial() {
            Swal.fire({
                title: 'Are you sure?',
                text: "You Want To Convert Material Unit ?",
                icon: 'warning',
                showCancelButton: true,
                toast: true,
                position: 'center',
                showConfirmButton: true,
                timer: 8000,
                timerProgressBar: true,
                confirmButtonColor: '#eda61a',
                cancelButtonColor: '#000000',
                confirmButtonText: 'Yes, Convert',
                cancelButtonText: 'Cancel',
                customClass: {
                    container: 'model-width-450px'
                },
            }).then((result) => {
                if (result.isConfirmed) {
                    var url = "{{ url('/newStockUnitConversion') }}";
                    window.location.href = url;
                }
            });
        }

        function deletedata(id) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                toast: true,
                position: 'center',
                showConfirmButton: true,
                timer: 8000,
                timerProgressBar: true,
                confirmButtonColor: '#ff0000',
                cancelButtonColor: '#000000',
                confirmButtonText: 'Delete',
                cancelButtonText: 'Cancel',
                customClass: {
                    container: 'model-width-450px'
                },
            }).then((result) => {
                if (result.isConfirmed) {
                    var url = "{{ url('/deleteStockUnitConversion/?id=') }}" + id;
                    window.location.href = url;
                }
            });
        }
    </script>
@endsection
