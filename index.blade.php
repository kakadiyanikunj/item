@php
    if (Auth::guard('admin')->check()) {
        $layout = 'layouts.app';
    }else if (Auth::guard('candidate')->check()) {
        $layout = 'frontend.generate_task.layouts.app';
    } else {
        $layout = 'company.layouts.main';
    }
@endphp
@extends($layout)

@section('title', 'Items Master List')

@section('breadCrumb')
<a href="@if (Auth::guard('admin')->check()) {{ route('home') }} @elseif (Auth::guard('candidate')->check()) {{ route('frontend.task.dashboard') }} @else  {{ route('recruiter.panel.home') }} @endif"
    class="breadcrumb-item"><i class="icon-home2 mr-2"></i> Home</a>
    <span class="breadcrumb-item active">Items Master List</span>
@endsection

@section('customCss')
    <style>
        .image-preview{
            color: #828393 !important;
        }

        .image-model{
            width: 500px;
        }
        @media (max-width: 768px) {
            .image-model{
                width: 250px;
            }
        }
    </style>
@endsection

@section('content')
    <div class="row">
        <div class="col-lg-12 col-sm-12">
            @if (auth('recruiter')->check())
                <h5 class="my-4">Items Master List c</h5>
            @endif
            <div class="card">
                <div class="card-datatable table-responsive">
                    <table class="table datatable-button-init-basic" id="list-table">
                        <thead class="bg-light">
                            <tr>
                                <th>No</th>
                                <th>Image</th>
                                <th>Group</th>
                                <th>Category</th>
                                <th>Sub Category</th>
                                <th>Item Code</th>
                                <th>Name</th>
                                <th>Brand</th>
                                <th>Color</th>
                                <th>Size</th>
                                <th>Unit</th>
                                <th>Qnty</th>
                                <th>Min Stock Alert</th>
                                <th>Status</th>
                                <th>Created At</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>

            <div class="modal modal-transparent fade" id="modals-transparent" tabindex="-1">
                <div class="modal-dialog modal-lg modal-dialog-centered modal-simple">
                    <div class="modal-content">
                        <div class="model-header text-center">
                            <a href="javascript:void(0);" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></a>
                            <span class="text-large text-white fw-bold image-text"></span>
                        </div>
                        <div class="modal-body text-center">
                            <img src="" class="image-model" alt="Image not uploaded">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('customJs')
    @if (!auth('recruiter')->check())
        <script src="{{ asset('assets/js/plugins/tables/datatables/datatables.min.js')}}"></script>
        <script src="{{ asset('assets/js/plugins/tables/datatables/datatable-init.min.js')}}"></script>
        <script src="{{ asset('assets/js/plugins/tables/datatables/extensions/buttons.min.js')}}"></script>
        <script src="{{ asset('assets/js/plugins/tables/datatables/extensions/responsive.min.js')}}"></script>
    @endif
    <script src="{{asset('assets/js/plugins/media/glightbox.min.js')}}"></script>
    <script src="{{asset('assets/js/plugins/media/gallery.js')}}"></script>
    <script>
        $(document).on('click', '.image-preview', function(e) {
            e.preventDefault();
            var imageSrc = $(this).attr("src");
            var documentName = $(this).data("text");
            $('.image-text').text(documentName);
            $('.image-model').attr('src', imageSrc);
        });

        $(function () {
            var i = 1;

            function generateImageUrl(filename) {
                return filename ? "{{ storage_url(config('constants.asset_management_items'),'') }}" + filename : "{{ asset(config('constants.default_no_image')) }}";
            }

            var table = $('#list-table').DataTable({
                processing: true,
                serverSide: true,
                scrollX: true,
                order: [2, 'desc'],
                "lengthMenu": [15, 25, 50, 75, 100],
                // responsive: true,
                "drawCallback": function (oSettings, json) {
                    Gallery.init();
                },
                ajax: {
                    "url": "{{ route('recruiter.asset.items.index') }}",
                    @if (!auth('recruiter')->check())
                        beforeSend: function () {
                            table_loder_start();
                        },
                        complete: function () {
                            table_loder_end();
                        },
                    @endif
                },
                columns: [
                    {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                    {data: 'image', name: 'image', orderable: false, searchable: false},
                    {data: 'group_name', name: 'group_name'},
                    {data: 'category_name', name: 'category_name'},
                    {data: 'sub_category_name', name: 'sub_category_name'},
                    {data: 'item_code', name: 'item_code'},
                    {data: 'name', name: 'name'},
                    {data: 'brand', name: 'brand'},
                    {data: 'color', name: 'color'},
                    {data: 'size', name: 'size'},
                    {data: 'unit', name: 'unit'},
                    {data: 'qnty', name: 'qnty'},
                    {data: 'min_stock_alert', name: 'min_stock_alert'},
                    {data: 'status', name: 'status', orderable: false, searchable: false},
                    {data: 'created_at', name: 'created_at'},
                    {data: 'id', name: 'id', orderable: false, searchable: false},
                ],
                columnDefs: [
                    {
                        "targets": '_all',
                        "className": 'text-center'
                    },
                    {
                        "targets": [1],
                        "render": function (data, type, row, meta) {
                            // return '<img src="{{asset('modules/recruiter/asset_managment/items/')}}/' + data + '" style="width:50px; height: 70px;" data-popup="lightbox" data-gallery="gallery1">'
                            return `<img src="${generateImageUrl(data)}" style="width:50px; height: 70px;" class="image-preview"  @if(auth('recruiter')->check()) data-bs-toggle="modal" data-bs-target="#modals-transparent" @else data-popup="lightbox" data-gallery="gallery1" @endif loading="lazy">`;
                        }
                    },
                    {
                        "targets": [8],
                        "render": function (data, type, row, meta) {
                            return '<input type="color" value="'+data+'" disabled>'
                        }
                    },
                    {
                        "targets": [15],
                        "width": "80px",
                        "render": function (data, type, row, meta) {
                            @if (auth('recruiter')->check())
                                var action = `<div class="d-inline-block">
                                                    <a href="javascript:;" class="btn btn-sm btn-text-secondary rounded-pill btn-icon dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                                                        <i class="mdi mdi-dots-vertical"></i>
                                                    </a>
                                                    <ul class="dropdown-menu dropdown-menu-end m-0">
                                                      {{-- <li><a href="{{ route('recruiter.asset.items.edit',':id') }}" class="dropdown-item"><i class="mdi mdi-pencil-outline"></i> Edit</a></li> --}}
                                                        <li><a href="javascript:void(0);" class="dropdown-item" onClick="deleteRecord('{{ route('recruiter.asset.items.delete',':id2') }}','list-table')"><i class="mdi mdi-trash-can-outline"></i> Delete</a></li>
                                                    </ul>
                                                </div>`;
                            @else
                                var action = '<div class="list-icons">' +
                                                '<div class="dropdown">' +
                                                    '<a href="#" class="list-icons-item" data-toggle="dropdown"> <i class="icon-menu9"></i> </a>' +
                                                    '<div class="dropdown-menu dropdown-menu-right">' +
                                                        // '<a href="{{route('recruiter.asset.items.edit',':id')}}" class="dropdown-item"><i class="icon-pencil"></i> Edit</a>' +
                                                        '<a href="{{route('recruiter.asset.items.delete',':id2')}}" class="dropdown-item" onClick="return confirm(\'Are you sure you want to delete?\')"><i class="icon-trash"></i> Delete</a>' +
                                                    '</div>' +
                                                '</div>' +
                                            '</div>';
                            @endif
                            NewAction = action.replace(':id', data);
                            NewAction = NewAction.replace(':id2', data);
                            return NewAction;
                        }
                    },
                ],
                @if (auth('recruiter')->check())
                    dom: `<"row mx-1"'
                        '<"col-sm-12 col-md-3" l>'
                        '<"col-sm-12 col-md-9"<"dt-action-buttons text-xl-end text-lg-start text-md-end text-start d-flex align-items-center justify-content-md-end justify-content-center flex-wrap mb-1 me-1"<"me-3"f>B>>'
                        '>t'
                        '<"row mx-2"'
                        '<"col-sm-12 col-md-6"i>'
                        '<"col-sm-12 col-md-6"p>'
                        '>`,
                @endif
                buttons: {
                    dom: {
                        button: {
                            @if (auth('recruiter')->check())
                                className: 'btn btn-primary me-1 mb-1',
                            @else
                                className: 'btn btn-light',
                            @endif
                        }
                    },
                    buttons: [
                        {
                            extend: 'copy',
                            exportOptions: {
                                columns: ':not(:nth-child(16))'
                            }
                        },
                        {
                            extend: 'csv',
                            exportOptions: {
                                columns: ':not(:nth-child(16))'
                            }
                        },
                        {
                            text: '<i class="icon-plus3 mdi mdi-plus"></i> Add Item',
                            init: function (dt, node, config) {
                                this.enable();
                            },
                            action: function (e, dt, button, config) {
                                window.location = "{{route('recruiter.asset.items.create')}}";
                            }
                        },
                    ]
                },
            });
        });
    </script>
@endsection
