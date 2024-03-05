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

@section('title', $title)

@section('breadCrumb')
<a href="@if (Auth::guard('admin')->check()) {{ route('home') }} @elseif (Auth::guard('candidate')->check()) {{ route('frontend.task.dashboard') }} @else  {{ route('recruiter.panel.home') }} @endif"
    class="breadcrumb-item"><i class="icon-home2 mr-2"></i> Home</a>
    <a href="{{ route('recruiter.asset.items.index') }}" class="breadcrumb-item">Items Master List</a>
    <span class="breadcrumb-item active">{{ $title }}</span>
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
        @if (auth('recruiter')->check())
            <h5 class="my-4">{{ $title }}</h5>
        @endif
        <div class="col-md-12 col-xs-12">
            <div class="card">
                <div class="card-body">
                    <div class="col-md-12" id="add-message"></div>
                    {!! Form::model($items, [
                        'route' => 'recruiter.asset.items.store',
                        'method' => 'POST',
                        'files' => 'true',
                        'enctype' => 'multipart/form-data',
                        'id' => 'add_items',
                    ]) !!}

                    <div class="row g-3 ">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="font-weight-semibold">Group <span class="text-danger">*</span></label>
                                {!! Form::select('asset_group_id', $group, $items->asset_group_id, [
                                    'class' => 'form-control select2',
                                    'placeholder' => 'Please select',
                                    'id' => 'asset_group_id',
                                    'onchange' => 'category(this.value)',
                                ]) !!}
                                <span class="text-danger" id="asset_group_id_error">{{ $errors->first('asset_group_id') }}</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="font-weight-semibold">Category <span class="text-danger">*</span></label>
                                {!! Form::select('asset_category_id', [], null, [
                                    'class' => 'form-control select2',
                                    'placeholder' => 'Please select',
                                    'id' => 'asset_category_id',
                                    'onchange' => 'sub_category(this.value,$("#asset_group_id :selected").val())',
                                ]) !!}
                                <span class="text-danger" id="asset_category_id_error">{{ $errors->first('asset_category_id') }}</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="font-weight-semibold">Sub Category <span class="text-danger">*</span></label>
                                {!! Form::select('asset_sub_category_id', [], null, [
                                    'class' => 'form-control select2',
                                    'placeholder' => 'Please select',
                                    'id' => 'asset_sub_category_id',
                                ]) !!}
                                <span class="text-danger" id="asset_sub_category_id_error">{{ $errors->first('asset_sub_category_id') }}</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="font-weight-semibold">Item Name<span class="text-danger">*</span></label>
                                {!! Form::text('name', null, [
                                    'placeholder' => 'Enter name',
                                    'class' => 'form-control',
                                ]) !!}
                                <span class="text-danger" id="name_error">{{ $errors->first('name') }}</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="font-weight-semibold">Brand<span class="text-danger">*</span></label>
                                {!! Form::text('brand', null, [
                                    'placeholder' => 'Enter brand',
                                    'class' => 'form-control',
                                ]) !!}
                                <span class="text-danger" id="brand_error">{{ $errors->first('brand') }}</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="font-weight-semibold">Color<span class="text-danger">*</span></label>
                                {!! Form::color('color', null, [
                                    'placeholder' => 'Enter color',
                                    'class' => 'form-control',
                                ]) !!}
                                <span class="text-danger" id="color_error">{{ $errors->first('color') }}</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="font-weight-semibold">Size<span class="text-danger">*</span></label>
                                {!! Form::text('size', null, [
                                    'placeholder' => 'Enter size',
                                    'class' => 'form-control number',
                                    'min' => "0",
                                ]) !!}
                                <span class="text-danger" id="size_error">{{ $errors->first('size') }}</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="font-weight-semibold">Unit<span class="text-danger">*</span></label>
                                {!! Form::text('unit', null, [
                                    'placeholder' => 'Enter unit',
                                    'class' => 'form-control',
                                ]) !!}
                                <span class="text-danger" id="unit_error">{{ $errors->first('unit') }}</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="font-weight-semibold">Min Stock Alert<span class="text-danger">*</span></label>
                                {!! Form::text('min_stock_alert', null, [
                                    'placeholder' => 'Enter min stock alert',
                                    'class' => 'form-control number',
                                    'min' => "0",
                                ]) !!}
                                <span class="text-danger" id="min_stock_alert_error">{{ $errors->first('min_stock_alert') }}</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="font-weight-semibold">Image</label>
                                <div class="input-group">
                                    <input type="file" name="image" class="form-control"
                                        accept="image/png, image/jpg, image/jpeg">
                                    @if ($items->id)
                                        <span class="input-group-append">
                                            {{-- <a href="{{asset('modules/recruiter/asset_managment/items/'.$items->image)}}" data-popup="lightbox" data-gallery="gallery1">
                                                <span class="input-group-text p-2"><i class="icon-eye"></i></span>
                                            </a> --}}
                                            <a class="input-group-text image-preview" id="imgPreview" href="{{ $items->image ? storage_url(config('constants.asset_management_items'), $items->image) : asset(config('constants.default_no_image')) }}"
                                                @if(auth('recruiter')->check()) data-bs-toggle="modal" data-bs-target="#modals-transparent" @else data-popup="lightbox" data-gallery="gallery1" @endif>
                                                <i class="icon-eye mdi mdi-eye-outline"></i>
                                            </a>
                                        </span>
                                    @endif
                                </div>
                                <span id="image_error" class="text-danger" id="image_error">{{ $errors->first('image') }}</span>
                            </div>
                        </div>
                        {{-- <div class="col-md-4">
                            <div class="form-group">
                                <label class="d-block font-weight-semibold">Status <span class="text-danger">*</span></label>
                                <div class="custom-control custom-radio custom-control-inline">
                                    <input type="radio" class="custom-control-input" name="status" id="status"
                                        value="1" checked {{ old('status', $items->status) == '1' ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="status">Active</label>
                                </div>
                                <div class="custom-control custom-radio custom-control-inline">
                                    <input type="radio" class="custom-control-input" name="status" id="status1"
                                        value="0" {{ old('status', $items->status) == '0' ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="status1">In Active</label>
                                </div>
                            </div>
                        </div>                         --}}
                        <div class="col-md-12">
                            <div class="d-flex justify-content-end align-items-center ml-2">
                                {!! Form::hidden('id', null) !!}
                                <button type="submit" class="btn btn-primary">Submit <i
                                        class="icon-paperplane ml-2"></i></button>
                            </div>
                        </div>
                    </div>
                    {!! Form::close() !!}
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
    <script src="{{ asset('assets/js/plugins/media/glightbox.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/media/gallery.js') }}"></script>
    <script>
        $(document).ready(function() {
            $(document).on('click', '.image-preview', function(e) {
                e.preventDefault();
                var imageSrc = $(this).attr("href");
                var documentName = $(this).data("text");
                $('.image-text').text(documentName);
                $('.image-model').attr('src', imageSrc);
            });

            $(".select2").select2().on('change', function() {
                $(this).valid();
            });
            category($('#asset_group_id :selected').val());
        });
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.5/jquery.validate.min.js"></script>
    <script>
         $('.number').keypress(
            function(event) {
                    if (event.keyCode == 46 || event.keyCode == 8) {
                        //do nothing
                    } else {
                        if (event.keyCode < 48 || event.keyCode > 57) {
                            event.preventDefault();
                        }
                    }
                }
            );

        $.validator.addMethod('filesize', function(value, element, param) {
            return this.optional(element) || (element.files[0].size <= param)
        }, 'File size must be less than 2 MB');

        $.validator.addMethod('imageExtension', function(value, element, param) {
            var extension = value.split('.').pop().toLowerCase();
            return this.optional(element) || $.inArray(extension, param) !== -1;
        }, 'Invalid file format. Only allowed file types is ["png", "jpg", "jpeg", "gif"]');

        $("#add_items").validate({
            errorPlacement: function(error, element) {
                $("#"+element.attr('name')+"_error").html(error);
            },
            errorClass: "error fail-alert",
            rules: {
                asset_group_id: {
                    required: true,
                },
                asset_category_id: {
                    required: true,
                },
                asset_sub_category_id: {
                    required: true,
                },
                name: {
                    required: true,
                },
                brand: {
                    required: true,
                },
                color: {
                    required: true,
                },
                size: {
                    required: true,
                },
                unit: {
                    required: true,
                },
                min_stock_alert: {
                    required: true,
                },
                image: {
                    imageExtension: ["jpg", "jpeg", "png", "gif"], // allowed image extensions
                    filesize: 2 * 1024 * 1024,
                },
            },
            messages: {
                asset_group_id: {
                    required: "Group is required.",
                },
                asset_category_id: {
                    required: "Category is required.",
                },
                asset_sub_category_id: {
                    required: "Sub category is required.",
                },
                name: {
                    required: "Name is required.",
                },
                brand: {
                    required: "Brand is required.",
                },
                color: {
                    required: "Color is required.",
                },
                size: {
                    required: "Size is required.",
                },
                unit: {
                    required: "Unit is required.",
                },
                min_stock_alert: {
                    required: "Min stock alert is required.",
                },
                image: {
                    imageExtension: "You're only allowed to upload jpeg or jpg or png or gif images.",
                    filesize: "File size must be less than 2 MB."
                }
            },
        });

        function category(id) {
            if (id) {
                $.ajax({
                    url: `{{ route('recruiter.asset.group') }}`,
                    type: "POST",
                    data: {
                        id: id,
                        '_token': '{{ csrf_token() }}'
                    },
                    success: function(result) {
                        var html2 = '';
                        html2 += '<option value="">Please Select</option>';
                        $.each(result.category, function(i, v) {
                            html2 += '<option " value="' + i + '">' + v + '</option>';
                        });
                        $('#asset_category_id').html(html2);
                        $("#asset_category_id").val('{{ $items->asset_category_id }}').select2();

                        sub_category($('#asset_group_id :selected').val(), $('#asset_category_id :selected')
                            .val());
                    }
                });
            } else {
                $('#asset_category_id').val('').html('<option value="">Please Select</option>');
                $('#asset_sub_category_id').val('').html('<option value="">Please Select</option>');
            }
        }

        function sub_category(group_id, category_id) {
            if (group_id && category_id) {
                $.ajax({
                    url: `{{ route('recruiter.asset.category') }}`,
                    type: "POST",
                    data: {
                        group_id: category_id,
                        category_id: group_id,
                        '_token': '{{ csrf_token() }}'
                    },
                    success: function(result) {
                        var html2 = '';
                        html2 += '<option value="">Please Select</option>';
                        $.each(result.category, function(i, v) {
                            html2 += '<option " value="' + i + '">' + v + '</option>';
                        });
                        $('#asset_sub_category_id').html(html2);
                        $("#asset_sub_category_id").val('{{ $items->asset_sub_category_id }}').select2();

                    }
                });
            } else {
                $('#asset_sub_category_id').val('').html('<option value="">Please Select</option>');
            }
        }
    </script>
@endsection
