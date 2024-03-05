@php
    $isBM = isset(auth('branchEmployee')->user()->role_id) && auth('branchEmployee')->user()->role_id === 95;
@endphp

@extends('branch-employee.layouts.app')

@section('title', 'Application')

@section('customCss')
    <style>
        .select2-search--inline {
            display: contents;
            /*this will make the container disappear, making the child the one who sets the width of the element*/
        }

        .select2-search__field:placeholder-shown {
            width: 100% !important;
            /*makes the placeholder to be 100% of the width while there are no options selected*/
        }
    </style>
@endsection

@section('breadCrumb')
    <a href="{{ route('branch.employee.home') }}" class="breadcrumb-item"><i class="icon-home2 mr-2"></i> Home</a>
    <span class="breadcrumb-item active">Application List</span>
@endsection

@section('content')
    <button type="button" id="hiddenButton" style="display: none;">Push State</button>
    <div
        class="card {{ isset(auth('branchEmployee')->user()->role_id) && in_array(auth('branchEmployee')->user()->role_id, [95, 93,103]) ? '' : 'd-none' }}">
        <div class="card-body">
            <div class="form-row">
                <div
                    class="form-group col-md-3 {{ isset(auth('branchEmployee')->user()->role_id) && auth('branchEmployee')->user()->role_id == 95 ? '' : 'd-none' }}">
                    <label for="team_leader_id">Team Leader:</label>
                    <select class="form-control select2" onchange="getCounsellor(this.value)" id="team_leader_id"
                        name="team_leader_id">
                        <option value="">Select Team Leader</option>
                        @if ($team_leader)
                            @foreach ($team_leader as $value)
                                <option value="{{ $value->id }}"
                                    {{ auth('branchEmployee')->user()->role_id == 95 ? '' : (auth('branchEmployee')->user()->id == $value->id ? 'selected' : '') }}>
                                    {{ $value->name }}</option>
                            @endforeach
                        @endif
                    </select>
                </div>
                <div
                    class="form-group col-md-3 {{ isset(auth('branchEmployee')->user()->role_id) && in_array(auth('branchEmployee')->user()->role_id, [95, 93,103]) ? '' : 'd-none' }}">
                    <label for="counsellor_id">Counsellor:</label>
                    <select class="form-control select2" id="counsellor_id" name="counsellor_id">
                        <option value="">Select Counsellor</option>
                        @if ($counsellor)
                            @foreach ($counsellor as $key => $value)
                                <option value="{{ $key }}">{{ $value }}</option>
                            @endforeach
                        @endif
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <table class="table datatable-button-init-basic" id="list-table">
            <thead class="bg-light">
                <tr>
                    <th>No</th>
                    <th>Name</th>
                    <th>Mobile No</th>
                    <th>Email Id</th>
                    <th>Parent</th>
                    <th>Team Leader Assign</th>
                    <th>Buddy Assign</th>
                    <th>Executive Assign</th>
                    <th>Trainee Assign</th>
                    <th>Candidate Status</th>
                    <th>Verified Status</th>
                    <th>Scheduled Date</th>
                    <th>Time</th>
                    <th>Verified On</th>
                    @canany(['application-list', 'application-edit'])
                        <th>Profile</th>
                        <th>AJM Resumes</th>
                        <th>Candidate Resumes</th>
                    @endcanany
                    <th>Whatsapp Resend</th>
                </tr>
            </thead>
        </table>
    </div>
    <div id="filter" class="modal modal-right fade" tabindex="-1">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-transparent align-items-center">
                    <h5 class="modal-title font-weight-semibold">Filter</h5>
                    <button type="button" class="btn btn-icon btn-light btn-sm border-0 rounded-pill ml-auto"
                        data-dismiss="modal"><i class="icon-cross2"></i></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label class="font-weight-semibold">Select Candidate Type</label>
                        <select class="form-control select-search" data-placeholder="Select Candidate Status"
                            id="candidate_type">
                            <option value="">Select Candidate Type</option>
                            <option value="2">Application </option>
                            <option value="0">Website</option>
                            <option value="1">BranchEmployee</option>
                            <option value="3">Weebhook</option>

                        </select>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-semibold">Select Candidate Status</label>
                        <select class="form-control select-search" id="cv_status_id">
                            <option value="">Select Candidate Status</option>
                            <option value="">Select Candidate Status</option>
                            <option value="5">Only Signup</option>
                            <option value="3">Incomplete Profile</option>
                            <option value="4">Completed profile</option>
                            <option value="2">Document Approved</option>
                            <option value="1">Verified Candidate</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-semibold">Select Status</label>
                        <select class="form-control select-search" data-placeholder="Select Status" id="candidate_status">
                            <option value="">Select Candidate Status</option>
                            <option value="1">Pending</option>
                            <option value="2">Approved</option>
                            <option value="3">Rejected</option>
                            <option value="4">Reuploaded</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-semibold">Buddy Assigned</label>
                        <select class="form-control select-search" data-placeholder="Select Status" id="buddy_assigned">
                            <option value="">Select Option</option>
                            <option value="1">Yes</option>
                            <option value="0">No</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-semibold">Scheduled Date</label>
                        <input type="text" name="date" class="form-control datepicker" placeholder="Select Date"
                            id="date" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label class="font-weight-semibold">Candidate Created From</label>
                        <input type="text" name="candidatedatefrom" class="form-control datepicker"
                            placeholder="Select Date" id="candidatedatefrom" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label class="font-weight-semibold">Candidate Created To</label>
                        <input type="text" name="candidatedateto" class="form-control datepicker"
                            placeholder="Select Date" id="candidatedateto" autocomplete="off">
                    </div>
                    <div class="modal-footer bg-transparent">
                        <button type="button" class="btn btn-danger" id="reset_filter">Reset</button>
                        <button type="button" class="btn btn-primary" id="get_filter">Search</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('customJs')
    <script src="{{ asset('assets/js/plugins/tables/datatables/datatables.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/tables/datatables/datatable-init.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/tables/datatables/extensions/buttons.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/tables/datatables/extensions/responsive.min.js') }}"></script>
    <script src="{{ asset('frontend/assets/js/datepicker/jquery-ui.js') }}"></script>
    <script>
        $(document).ready(function() {

            var filter_select = {{ $filter_select ?? 'null' }};
            if (filter_select === 1) {
                var today = new Date().toISOString().split('T')[0];
                $("#candidatedatefrom").val(today);
                $("#candidatedateto").val(today);
            } else if (filter_select === 2) {
                var today = new Date();
                var yesterday = new Date(today);
                yesterday.setDate(today.getDate() - 1); // Subtract one day from today's date
                var formattedYesterday = yesterday.toISOString().split('T')[0];
                $("#candidatedatefrom").val(formattedYesterday);
                $("#candidatedateto").val(formattedYesterday);
            }

            var fromDate = "{{ $fromDate ?? 'null' }}";
            var toDate = "{{ $toDate ?? 'null' }}";

            // Check if fromDate and toDate are not 'null' and not empty
            if (fromDate !== 'null' && fromDate !== '' && toDate !== 'null' && toDate !== '') {
                $("#candidatedatefrom").val(fromDate);
                $("#candidatedateto").val(toDate);
            }

            var tl = {{ $tl ?? 'null' }};
            if (tl !== null && tl !== '') {
                $("#team_leader_id").val(tl);
            }

            var budddy = {{ $buddy ?? 'null' }};
            if (budddy !== null && budddy !== '') {
                $("#counsellor_id").val(budddy);
            }

            var candidatetype_id = {{ $candidatetype_id ?? 'null' }}; // Total Application Candidate
            $("#candidate_type").val(candidatetype_id);

            var only_otp_verified = {{ $only_otp_verified ?? 'null' }}; // Only OTP verified Application
            if (only_otp_verified === 5) {
                $("#cv_status_id").val(only_otp_verified);
                $("#candidate_type").val(2);
            }
            var rejected_document = {{ $rejected_document ?? 'null' }}; // Rejected Candidate Document
            if (rejected_document === 0) {
                $("#candidate_type").val(2);
                $("#candidate_status").val(3);
            }

            var reuploaded_document = {{ $reuploaded_document ?? 'null' }}; // Reupload Candidate Document
            if (reuploaded_document === 0) {
                $("#candidate_type").val(2);
                $("#candidate_status").val(4);
            }
            var today_rejected_document =
                {{ $today_rejected_document ?? 'null' }}; //Today Rejected Candidate Document
            if (today_rejected_document === 0) {
                var today = new Date().toISOString().split('T')[0];
                $("#candidatedatefrom").val(today);
                $("#candidatedateto").val(today);
                $("#candidate_type").val(2);
                $("#candidate_status").val(3);
            }

            var today_reuploaded_document =
                {{ $today_reuploaded_document ?? 'null' }}; //Today Reupload Candidate Document
            if (today_reuploaded_document === 0) {
                var today = new Date().toISOString().split('T')[0];
                $("#candidatedatefrom").val(today);
                $("#candidatedateto").val(today);
                $("#candidate_type").val(2);
                $("#candidate_status").val(4);
            }

            var cvtype_id = {{ $cvtype_id ?? 'null' }}; // Incomplete Profile Application
            if (cvtype_id !== null && cvtype_id !== '') {
                $("#cv_status_id").val(cvtype_id);
                $("#candidate_type").val(2);
            }

            var today_signup_candidate =
                {{ $today_signup_candidate ?? 'null' }}; // Today Application Candidate Sign-up
            if (today_signup_candidate === 2) {
                var today = new Date().toISOString().split('T')[0];
                $("#candidate_type").val(today_signup_candidate);
                $("#candidatedatefrom").val(today);
                $("#candidatedateto").val(today);
            }

            var today_only_otp_verified = {{ $today_only_otp_verified ?? 'null' }}; // Today Only Otp Verified
            if (today_only_otp_verified === 5) {
                var today = new Date().toISOString().split('T')[0];
                $("#cv_status_id").val(today_only_otp_verified);
                $("#candidatedatefrom").val(today);
                $("#candidatedateto").val(today);
                $("#candidate_type").val(2);
            }

            var today_incompleteprofile = {{ $today_incompleteprofile ?? 'null' }}; // Today Incomplete Profile
            if (today_incompleteprofile === 3) {
                var today = new Date().toISOString().split('T')[0];
                $("#cv_status_id").val(today_incompleteprofile);
                $("#candidate_type").val(2);
                $("#candidatedatefrom").val(today);
                $("#candidatedateto").val(today);
            }

            var today_pending = {{ $today_pending ?? 'null' }}; // Today pending Verification
            if (today_pending === 4) {
                var today = new Date().toISOString().split('T')[0];
                $("#cv_status_id").val(today_pending);
                $("#candidate_type").val(2);
                $("#candidatedatefrom").val(today);
                $("#candidatedateto").val(today);
            }

            var today_whatsapp = {{ $today_whatsapp ?? 'null' }}; // Today pending whatsapp verification
            if (today_whatsapp === 2) {
                var today = new Date().toISOString().split('T')[0];
                $("#cv_status_id").val(today_whatsapp);
                $("#candidate_type").val(2);
                $("#candidatedatefrom").val(today);
                $("#candidatedateto").val(today);
            }

            var today_candidate_verified = {{ $today_candidate_verified ?? 'null' }}; // Today Verified Candidate
            if (today_candidate_verified === 1) {
                var today = new Date().toISOString().split('T')[0];
                $("#cv_status_id").val(today_candidate_verified);
                $("#candidate_type").val(2);
                $("#candidatedatefrom").val(today);
                $("#candidatedateto").val(today);
            }
        });
    </script>
    <script>
        $('#candidatedatefrom').datepicker({
            dateFormat: 'yy-mm-dd',
            onSelect: function() {
                var formattedDate = $.datepicker.formatDate('yy-mm-dd', $(this).datepicker(
                    'getDate'));
                // Set the minimum date of the to datepicker
                $('#candidatedateto').datepicker('option', 'minDate', formattedDate);
            }
        });
        $('#candidatedateto').datepicker({
            dateFormat: 'yy-mm-dd',
        });

        $(function() {
            $(".select2").select2();
            $(".select-search").select2({
                placeholder: 'Select Options',
            });

            var i = 1;
            var table = $('#list-table').DataTable({
                processing: true,
                serverSide: true,
                order: [0, 'desc'],
                "lengthMenu": [15, 25, 50, 75, 100],
                responsive: true,
                ajax: {
                    "url": "{{ route('branche.employee.application.index') }}",
                    data: function(data) {
                        data.candidate_type = $('#candidate_type').val();
                        data.cv_status_id = $('#cv_status_id').val();
                        data.candidate_status = $('#candidate_status').val();
                        data.buddy_assigned = $('#buddy_assigned').val();
                        data.date = $('#date').val();
                        data.candidatedatefrom = $('#candidatedatefrom').val();
                        data.candidatedateto = $('#candidatedateto').val();

                        data.team_leader_id = $('#team_leader_id').val();
                        data.counsellor_id = $('#counsellor_id').val();
                    },
                    beforeSend: function() {
                        table_loder_start();
                    },
                    complete: function() {
                        table_loder_end();
                    },
                },
                columns: [{
                        data: 'DT_RowIndex',
                        name: 'DT_RowIndex'
                    },
                    {
                        data: 'name',
                        name: 'name'
                    },
                    {
                        data: 'mobile_no',
                        name: 'mobile_no'
                    },
                    {
                        data: 'email_id',
                        name: 'email_id'
                    },
                    {
                        data: 'parent',
                        name: 'parent'
                    },
                    {
                        data: 'branch_team_leader',
                        name: 'branch_team_leader'
                    },
                    {
                        data: 'branch_counsellor',
                        name: 'branch_counsellor'
                    },
                    {
                        data: 'branch_executive',
                        name: 'branch_executive'
                    },
                    {
                        data: 'branch_trainee',
                        name: 'branch_trainee'
                    },
                    {
                        data: 'candidate_status',
                        name: 'candidate_status'
                    },
                    {
                        data: 'status',
                        name: 'status'
                    },
                    {
                        data: 'date',
                        name: 'date'
                    },
                    {
                        data: 'time',
                        name: 'time'
                    },
                    {
                        data: 'varified_at',
                        name: 'varified_at'
                    },
                    @canany(['application-list', 'application-edit'])
                        {
                            data: 'resumes',
                            name: 'profile',
                            orderable: false,
                            searchable: false
                        }, {
                            data: 'resumes',
                            name: 'resumes',
                            orderable: false,
                            searchable: false
                        }, {
                            data: 'candidate_resume',
                            name: 'candidate_resume',
                            orderable: false,
                            searchable: false
                        },
                    @endcanany {
                        data: 'resend_whatsapp',
                        name: 'resend_whatsapp',
                        orderable: false,
                        searchable: false,
                        visible: "{{ $isBM }}",
                    }
                ],
                columnDefs: [
                    @if (Auth::guard('branchEmployee')->user()->role_id == 95)
                        {
                            "targets": [5],
                            "render": function(data, type, row, meta) {
                                if (row.stage_status) {
                                    return data;
                                } else {
                                    var html2 = '';
                                    html2 +=
                                        '<select class="form-control select2" id="branchTeamLeader" data-placeholder="Please Select Branch Team Leader" onchange="branchTeamLeader(this.value,`' +
                                    row.id + '`,this)" style="width: 250px;">';
                                    html2 += '<option value="">Please Select</option>';
                                    $.each(data, function(i, v) {
                                        var selected = "";
                                        if (i == row.branch_team_leader_id) {
                                            selected = "selected";
                                        }
                                        html2 += '<option ' + selected + ' value="' + i +
                                            '">' + v + '</option>';
                                    });
                                    html2 += '</select>';
                                    return html2;
                                }
                            }
                        },
                    @endif
                    @if (Auth::guard('branchEmployee')->user()->role_id == 93 || Auth::guard('branchEmployee')->user()->role_id == 103 || Auth::guard('branchEmployee')->user()->role_id == 95)
                        {
                            "targets": [6],
                            "render": function(data, type, row, meta) {
                                if (row.stage_status) {
                                    return data;
                                } else {
                                    var html = '';
                                    html +=
                                        '<select class="form-control select2" id="branchCounsellor" data-placeholder="Please Select Branch Buddy" onchange="branchCounsellor(this.value,`' +
                                    row.id + '`)" style="width: 250px;">';
                                    html += '<option value="">Please Select</option>';
                                    $.each(data, function(i, v) {
                                        var selected = "";
                                        if (i == row.branch_counsellor_id) {
                                            selected = "selected";
                                        }
                                        html += '<option ' + selected + ' value="' + i +
                                            '">' + v + '</option>';
                                    });
                                    html += '</select>';
                                    return html;
                                }
                            }
                        },
                    @endif
                    @if (Auth::guard('branchEmployee')->user()->role_id == 89 || Auth::guard('branchEmployee')->user()->role_id == 102)
                        {
                            "targets": [7],
                            "render": function(data, type, row, meta) {
                                var html2 = '';
                                html2 +=
                                    '<select class="form-control select2" id="branchExecutive" data-placeholder="Please Select Branch" onchange="branchExecutive(this.value,`' +
                                row.id + '`)" style="width: 250px;">';
                                html2 += '<option value="">Please Select</option>';
                                $.each(data, function(i, v) {
                                    var selected = "";
                                    if (i == row.branch_executive_id) {
                                        selected = "selected";
                                    }
                                    html2 += '<option ' + selected + ' value="' + i + '">' +
                                        v + '</option>';
                                });
                                html2 += '</select>';
                                return html2;
                            }
                        },
                    @endif
                    @if (Auth::guard('branchEmployee')->user()->role_id == 92)
                        {
                            "targets": [8],
                            "render": function(data, type, row, meta) {
                                var html4 = '';
                                html4 +=
                                    '<select class="form-control select2" id="branchTrainee" data-placeholder="Please Select Branch" onchange="branchTrainee(this.value,`' +
                                row.id + '`)">';
                                html4 += '<option value="">Please Select</option>';
                                $.each(data, function(i, v) {
                                    var selected = "";
                                    if (i == row.branch_trainee_id) {
                                        selected = "selected";
                                    }
                                    html4 += '<option ' + selected + ' value="' + i + '">' +
                                        v + '</option>';
                                });
                                html4 += '</select>';
                                return html4;
                            }
                        },
                    @endif
                    @if (Auth::guard('branchEmployee')->user()->role_id != 99)
                        {
                            "targets": [9],

                            "render": function(data, type, row, meta) {
                                // if (data == "0") {
                                //     return '<span class="update-status badge badge-secondary">Only CV</span>';
                                // } else if (data == 1) {
                                //     return '<span class="update-status badge badge-secondary">Verified CV</span>';
                                // } else if (data == 2) {
                                //     return '<span class="update-status badge badge-primary">Only Candidate</span>';
                                // } else {
                                //     return '<span class="update-status badge badge-primary">Verified Candidate</span>';
                                // }
                                return `<span class="update-status badge ${row.profileClass} text-white">${row.profileStatus}</span>`;
                            }
                        },
                    @endif
                    @canany(['application-list', 'application-edit'])
                        {
                            "targets": [14],
                            "width": "80px",
                            "render": function(data, type, row, meta) {

                                // if (data == 0) {
                                //     var action =
                                //         '<a href="{{ route('candidate.personal-details', [':id', ':apt_id']) }}" class="btn badge-warning">Pending</a>';
                                // } else {
                                var action =
                                    '<a href="{{ route('candidate.profile', [':id', ':apt_id']) }}" class="btn btn-info">View</a>';
                                // }
                                NewAction = action.replace(':id', row.candidate_id);
                                NewAction = NewAction.replace(':apt_id', row.id);
                                return NewAction;
                            }
                        }, {
                            "targets": [15],
                            "width": "80px",
                            "render": function(data, type, row, meta) {

                                if (row.overall != 0) {
                                    if (data == 0) {
                                        var action =
                                            '<a href="{{ route('candidate.profile', [':id', ':apt_id']) }}" class="btn btn-warning">Pending</a>';

                                    } else {
                                        var action =
                                            '<a href="{{ route('candidate.resume', [':id', ':apt_id']) }}" target="_blank" class="btn btn-info">View</a>';
                                    }
                                    NewAction = action.replace(':id', row.candidate_id);
                                    NewAction = NewAction.replace(':apt_id', row.id);
                                    return NewAction;
                                } else {
                                    return '-';
                                }
                            }
                        }, {
                            "targets": [16],
                            "width": "80px",
                            "render": function(data, type, row, meta) {
                                if (row.can_resume) {
                                    return '<a href="' + data +
                                        '" target="_blank" class="btn btn-info">View</a>';
                                } else {
                                    return '-';
                                }
                            }
                        }, {
                            "targets": [17],
                            "width": "80px",
                            "render": function(data, type, row, meta) {
                                if (data) {
                                    return '<button type="button" class="btn btn-success" onclick="resendWhatsapp(\'' +
                                        row.candidate_id + '\')">Resend</button>';
                                } else {
                                    return '-';
                                }
                            }
                        },
                    @endcanany
                ],
                buttons: {
                    dom: {
                        button: {
                            className: 'btn btn-light'
                        }
                    },
                    buttons: [{
                            extend: 'copy'
                        },
                        {
                            extend: 'csv',
                            action: function() {
                                var csvContent = "data:text/csv;charset=utf-8,";
                                var rows = [];

                                // Get table headers
                                var headers = [];
                                $(table.table().header()).find('th').each(function() {
                                    headers.push($(this).text());
                                });
                                rows.push(headers.join(','));

                                table.rows().every(function(rowIdx, tableLoop, rowLoop) {
                                    var rowData = [];
                                    $(this.node()).find('td').each(function(index) {
                                        if ($(this).find('select').length) {
                                            var selectedValue = $(this).find('select option:selected').text();
                                            rowData.push(selectedValue);
                                        } else {
                                            rowData.push($(this).text());
                                        }
                                    });
                                    rows.push(rowData.join(','));
                                });

                                csvContent += rows.join('\n');

                                var encodedUri = encodeURI(csvContent);
                                var link = document.createElement("a");
                                link.setAttribute("href", encodedUri);
                                link.setAttribute("download", "application_data.csv");
                                document.body.appendChild(link);
                                link.click();
                            }
                        },
                        {
                            text: '<i class="icon-plus3"></i> Add Only CV',
                            init: function(dt, node, config) {
                                @can('application-create')
                                    this.enable();
                                @else
                                    this.remove();
                                @endcan
                            },
                            action: function(e, dt, button, config) {
                                window.location = "{{ route('application.create.only.cv') }}";
                            }
                        },
                        {
                            text: '<i class="icon-filter4"></i> Filter',
                            className: 'btn btn-light',
                            action: function(e, dt, button, config) {
                                $("#filter").modal('show');
                            }
                        },
                    ]
                },
            });
            /* if ('{{ !Auth::guard('admin')->check() }}') {
                table.columns([15]).visible(false);
                table.columns([16]).visible(false);
            } */
            if ('{{ Auth::guard('branchEmployee')->user()->role_id == 93 || Auth::guard('branchEmployee')->user()->role_id == 103 }}') {
                table.columns([5, 7, 8]).visible(false);
            }
            if ('{{ Auth::guard('branchEmployee')->user()->role_id == 89 || Auth::guard('branchEmployee')->user()->role_id == 102 }}') {
                table.columns([5, 6, 7, 8]).visible(false);
            }
            if ('{{ Auth::guard('branchEmployee')->user()->role_id == 92 }}') {
                table.columns([5, 6, 7]).visible(false);
            }
            if ('{{ Auth::guard('branchEmployee')->user()->role_id == 94 }}') {
                table.columns([5, 6, 7, 8]).visible(false);
            }
            if ('{{ Auth::guard('branchEmployee')->user()->role_id == 95 }}') {
                table.columns([7, 8]).visible(false);
            }

            $('#get_filter').click(function() {
                table.draw();
                $("#filter").modal('hide');
            });

            $('#reset_filter').click(function() {
                $('#candidate_type').val(null).trigger('change');
                $('#cv_status_id').val(null).trigger('change');
                $('#candidate_status').val(null).trigger('change');
                $('#buddy_assigned').val(null).trigger('change');
                $('#date').val(null).trigger('change');
                $('#candidatedatefrom').val(null).trigger('change');
                $('#candidatedateto').val(null).trigger('change');
                $('#team_leader_id').val(null).trigger('change');
                $('#counsellor_id').val(null).trigger('change');

                $.get('/branchemployee/application/appointments/clearFilter', function(response) {
                    // Handle the response, e.g., show a success message
                    // console.log(response.message);
                });

                table.draw();
                $("#filter").modal('hide');
            });
        });

        function conforms_status(status, id) {
            if (status == 0) {
                $("#confirmed").modal('show');
                $("#approve").attr("onclick", 'change_status(1,\'' + id + '\')')
                $("#cancel").attr("onclick", 'change_status(3,\'' + id + '\')')
            }
        }

        function change_status(status, id) {
            $.ajax({
                url: "{{ route('appointment.change.status') }}",
                type: "POST",
                data: {
                    status: status,
                    id: id,
                    '_token': "{{ csrf_token() }}"
                },
                success: function(result) {
                    if (result.message == "success") {
                        new PNotify({
                            text: 'Status change successfully',
                            addclass: 'bg-success text-white border-success',
                        });
                        $("#confirmed").modal('hide');
                        $('#list-table').DataTable().draw();
                    } else {
                        new PNotify({
                            text: 'Something was wrong please try again',
                            addclass: 'bg-danger text-white border-danger',
                        });
                    }
                }
            });
        }

        function get_amount(id) {
            $.ajax({
                url: "{{ route('appointment.get.amount') }}",
                type: "GET",
                data: {
                    id: id
                },
                success: function(result) {
                    $("#amount").val(result);
                }
            });
        }

        function payment_status(id, candidate_id) {
            $("#appointment_id").val(id);
            $("#candidate_id").val(candidate_id);
            $("#paynow").modal('show');
        }
        $(function() {
            $(".datepicker").datepicker({
                /*  "minDate": new Date(), */
                dateFormat: 'yy-mm-dd',
            });
        });

        function branchTeamLeader(branch_team_leader_id, id, event) {
            $.ajax({
                url: `{{ route('branche.employee.application.branchTeamleader.assign') }}`,
                type: "POST",
                data: {
                    id: id,
                    branch_team_leader_id: branch_team_leader_id,
                    '_token': '{{ csrf_token() }}'
                },
                success: function(result) {
                    var html = '';
                    html +=
                        '<select class="form-control select2" id="branchCounsellor" data-placeholder="Please Select Branch Buddy" onchange="branchCounsellor(this.value,`' +
                    id + '`)" style="width: 250px;">';
                    html += '<option value="">Please Select</option>';
                    $.each(result.branchCounsellor, function(i, v) {
                        html += '<option value="' + i + '">' + v + '</option>';
                    });
                    html += '</select>';
                    if (result.status == "success") {
                        new PNotify({
                            text: result.message,
                            addclass: 'bg-success text-white border-success',
                        });
                        $(event).parent('td').next('td').html(html);
                        $('#branchCounsellor').select2();
                    } else {
                        new PNotify({
                            text: result.message,
                            addclass: 'bg-danger text-white border-danger',
                        });
                    }
                }
            });
        }

        function branchCounsellor(branch_counsellor_id, id) {
            $.ajax({
                url: `{{ route('branche.employee.application.branchCounsellor.assign') }}`,
                type: "POST",
                data: {
                    id: id,
                    branch_counsellor_id: branch_counsellor_id,
                    '_token': '{{ csrf_token() }}'
                },
                success: function(result) {
                    if (result.status == "success") {
                        new PNotify({
                            text: result.msg,
                            addclass: 'bg-success text-white border-success',
                        });
                        $('#list-table').DataTable().ajax.reload();
                    } else {
                        new PNotify({
                            text: result.msg,
                            addclass: 'bg-danger text-white border-danger',
                        });
                    }
                }
            });
        }

        function branchExecutive(branch_executive, id) {
            $.ajax({
                url: `{{ route('branche.employee.application.branchExecutive.assign') }}`,
                type: "POST",
                data: {
                    id: id,
                    branch_executive: branch_executive,
                    '_token': '{{ csrf_token() }}'
                },
                success: function(result) {
                    if (result.status == "success") {
                        new PNotify({
                            text: result.msg,
                            addclass: 'bg-success text-white border-success',
                        });
                        $('#list-table').DataTable().ajax.reload();
                    } else {
                        new PNotify({
                            text: result.msg,
                            addclass: 'bg-danger text-white border-danger',
                        });
                    }
                }
            });
        }

        function branchTrainee(branch_trainee, id) {
            $.ajax({
                url: `{{ route('branche.employee.application.branchTrainee.assign') }}`,
                type: "POST",
                data: {
                    id: id,
                    branch_trainee: branch_trainee,
                    '_token': '{{ csrf_token() }}'
                },
                success: function(result) {
                    if (result.status == "success") {
                        new PNotify({
                            text: result.msg,
                            addclass: 'bg-success text-white border-success',
                        });
                        $('#list-table').DataTable().ajax.reload();
                    } else {
                        new PNotify({
                            text: result.msg,
                            addclass: 'bg-danger text-white border-danger',
                        });
                    }
                }
            });
        }

        function getCounsellor(team_leader_id) {
            $('#counsellor_id').empty();
            var dataTable = $('#list-table').DataTable();
            dataTable.draw();
            $.ajax({
                url: `{{ route('team_earnings_details.get_counsellor_by_team_leader_id') }}`,
                type: "POST",
                data: {
                    team_leader_id: team_leader_id,
                    '_token': '{{ csrf_token() }}'
                },
                success: function(result) {
                    if (result.data) {
                        let counsellor_option_html = '<option value="">Select Counsellor</option>';
                        $.each(result.data, function(item, value) {
                            counsellor_option_html +=
                                `<option value="${item}">${value}</option>`;
                        });

                        $('#counsellor_id').append(counsellor_option_html);
                    }
                }
            });
        }

        $(document).on('change', '#counsellor_id', function(e) {
            e.preventDefault();
            var dataTable = $('#list-table').DataTable();
            dataTable.draw();
        });

        // Trigger the button click event on page load
        document.addEventListener("DOMContentLoaded", function() {
            var hiddenButton = document.getElementById("hiddenButton");
            hiddenButton.click();
        });

        var customBackUrl = "{{ route('branch.employee.home') }}";
        // Push a unique state to history when the page loads
        history.pushState({
            navigated: true
        }, "", window.location.href);

        window.addEventListener("popstate", function(event) {
            if (sessionStorage.getItem('navigatedFromDashboardBranchEmployee')) {
                sessionStorage.removeItem('navigatedFromDashboardBranchEmployee');
                $.get('/branchemployee/application/appointments/clearFilter', function(response) {});
                window.location.href = customBackUrl;
            }
        });
    </script>

@endsection
