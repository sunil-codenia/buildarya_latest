@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'Support Tickets'])

    <div class="row clearfix">
        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="card">
                <div class="header">
                    <h2><strong>Support Tickets</strong> List &nbsp;<i class="zmdi zmdi-info info-hover"></i>
                        <div class="info-content">Manage and create support tickets for any issues or queries.</div>
                    </h2>
                    <ul class="header-dropdown">
                        <li>
                            <button class="btn btn-primary btn-round waves-effect" data-toggle="modal" data-target="#createTicketModal" style="color: white !important;">
                                <i class="zmdi zmdi-plus"></i> Create Ticket
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="body">
                    @if (session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif
                    @if (session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif
                    @if ($error)
                        <div class="alert alert-warning">
                            <strong>Notice:</strong> {{ $error }}
                        </div>
                    @endif

                    @if(empty($tickets))
                        <div class="text-center p-5" style="padding: 40px 0;">
                            <i class="zmdi zmdi-receipt text-muted" style="font-size: 48px; opacity: 0.5; margin-bottom: 15px; display: block;"></i>
                            <p class="text-muted" style="font-size: 16px;">No support tickets found.</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered" id="ticketsTable">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;">#</th>
                                        <th>Subject</th>
                                        <th>Created By</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $rowIndex = 1; @endphp
                                    @foreach($tickets as $ticket)
                                        @php
                                            $st = strtolower($ticket['status'] ?? 'open');
                                            $badgeClass = 'badge-primary';
                                            if ($st === 'resolved') $badgeClass = 'badge-success';
                                            elseif ($st === 'in progress') $badgeClass = 'badge-warning';
                                            elseif ($st === 'closed') $badgeClass = 'badge-default';
                                        @endphp
                                        <tr>
                                            <td>{{ $rowIndex++ }}</td>
                                            <td><strong>{{ $ticket['subject'] }}</strong></td>
                                            <td>{{ $ticket['created_by_name'] }}</td>
                                            <td>{{ \Carbon\Carbon::parse($ticket['createdAt'])->format('d M Y, h:i A') }}</td>
                                            <td>
                                                <span class="badge {{ $badgeClass }}" style="font-size: 11px; padding: 4px 8px; text-transform: uppercase;">
                                                    {{ $ticket['status'] }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <a href="{{ route('tickets.show', $ticket['id']) }}" 
                                                   class="btn btn-info btn-round btn-sm waves-effect">
                                                    <i class="zmdi zmdi-eye"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

@endsection

@section('models')
    <!-- Create Ticket Modal -->
    <div class="modal fade" id="createTicketModal" tabindex="-1" role="dialog" style="z-index: 99999;">
        <div class="modal-dialog" role="document">
            <form action="{{ route('tickets.store') }}" method="POST" class="form">
                @csrf
                <div class="modal-content" style="border-radius: 15px; overflow: visible; border: none;">
                    <div class="modal-header p-4" style="background: linear-gradient(135deg, #764ba2 0%, #667eea 100%); color: white; border-radius: 15px 15px 0 0;">
                        <h4 class="title m-0" style="font-weight: bold; color: white !important;"><i class="zmdi zmdi-receipt mr-2"></i> Create Support Ticket</h4>
                    </div>
                    <div class="modal-body p-4">
                        <div class="form-group mb-3">
                            <label class="font-weight-bold" style="color: #555;">Subject <span class="text-danger">*</span></label>
                            <input type="text" name="subject" class="form-control form-control-lg" required placeholder="Enter ticket subject" style="border-radius: 8px;">
                        </div>
                        <div class="form-group mb-3">
                            <label class="font-weight-bold" style="color: #555;">Description <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control" rows="5" required placeholder="Describe your issue in detail..." style="border-radius: 8px;"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer p-4" style="border-top: 1px solid #eee;">
                        <button type="submit" class="btn btn-primary btn-round waves-effect" style="background: linear-gradient(135deg, #764ba2 0%, #667eea 100%); border: none;">SUBMIT TICKET</button>
                        <button type="button" class="btn btn-danger btn-simple btn-round waves-effect" data-dismiss="modal">CLOSE</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        $(document).ready(function() {
            if ($('#ticketsTable').length) {
                $('#ticketsTable').DataTable({
                    responsive: true,
                    "order": [],
                    "oLanguage": {
                        "sSearch": '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-search"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>',
                        "sSearchPlaceholder": "Search Tickets...",
                        "sLengthMenu": "Results :  _MENU_",
                    }
                });
            }
        });
    </script>
@endsection
