@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'Ticket Details'])

    <div class="row clearfix">
        <div class="col-lg-8 col-md-12">
            <div class="card">
                <div class="header">
                    <h2><strong>{{ $ticket['subject'] }}</strong>
                        @php
                            $st = strtolower($ticket['status'] ?? 'open');
                            $badgeClass = 'badge-primary';
                            if ($st === 'resolved') $badgeClass = 'badge-success';
                            elseif ($st === 'in progress') $badgeClass = 'badge-warning';
                            elseif ($st === 'closed') $badgeClass = 'badge-default';
                        @endphp
                        <a href="{{ route('tickets.index') }}" class="btn btn-primary btn-round waves-effect float-right ml-3" style="margin-top: -10px; margin-left: 10px; color: white !important; font-size: 14px; padding: 8px 16px;">
                            <i class="zmdi zmdi-arrow-left"></i> Back
                        </a>
                        <span class="badge {{ $badgeClass }} float-right" style="margin-top: 2px;">{{ $ticket['status'] }}</span>
                    </h2>
                </div>
                <div class="body">
                    @if (session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif
                    @if (session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    <div class="ticket-description bg-light p-3 rounded mb-4" style="background-color: #f8f9fa; padding: 15px; border-radius: 8px;">
                        <p class="mb-0 text-muted" style="font-size: 12px;">Reported by {{ $ticket['created_by_name'] }} on {{ \Carbon\Carbon::parse($ticket['createdAt'])->format('d M Y, h:i A') }}</p>
                        <hr>
                        <p style="white-space: pre-wrap;">{{ $ticket['description'] }}</p>
                    </div>

                    <h4>Replies</h4>
                    <div class="replies-container">
                        @if(empty($ticket['replies']))
                            <p class="text-muted">No replies yet.</p>
                        @else
                            @foreach($ticket['replies'] as $reply)
                                <div class="media mb-4 p-3 rounded" style="{{ $reply['is_admin_reply'] ? 'background-color: #e3f2fd; border-left: 4px solid #2196F3;' : 'background-color: #f1f8e9; border-left: 4px solid #8BC34A;' }}">
                                    <div class="media-body">
                                        <h5 class="mt-0 mb-1" style="font-size: 14px;">
                                            {{ $reply['is_admin_reply'] ? 'Support Team' : ($reply['replied_by_name'] ?? 'You') }}
                                            <small class="text-muted float-right" style="font-size: 11px;">
                                                {{ \Carbon\Carbon::parse($reply['created_at'])->format('d M Y, h:i A') }}
                                            </small>
                                        </h5>
                                        <p style="white-space: pre-wrap; margin-bottom: 0;">{{ $reply['reply_text'] }}</p>
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>

                    @if(strtolower($ticket['status']) !== 'closed' && strtolower($ticket['status']) !== 'resolved')
                        <hr>
                        <form action="{{ route('tickets.reply', $ticket['id']) }}" method="POST">
                            @csrf
                            <div class="form-group">
                                <label><strong>Add a Reply</strong></label>
                                <textarea name="reply_text" class="form-control" rows="4" required placeholder="Type your message here..."></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary btn-round waves-effect">Post Reply</button>
                        </form>
                    @else
                        <div class="alert alert-info mt-4">
                            This ticket is marked as {{ $ticket['status'] }}. You cannot add new replies.
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-md-12">
            <div class="card">
                <div class="header">
                    <h2><strong>Ticket</strong> Info</h2>
                </div>
                <div class="body">
                    <ul class="list-group">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Ticket ID
                            <span class="badge badge-default badge-pill">#{{ str_pad($ticket['id'], 5, '0', STR_PAD_LEFT) }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Status
                            <span class="badge {{ $badgeClass }} badge-pill">{{ $ticket['status'] }}</span>
                        </li>
                        <li class="list-group-item">
                            <small class="text-muted">Created</small><br>
                            {{ \Carbon\Carbon::parse($ticket['createdAt'])->format('d M Y, h:i A') }}
                        </li>
                        <li class="list-group-item">
                            <small class="text-muted">Last Updated</small><br>
                            {{ \Carbon\Carbon::parse($ticket['updatedAt'])->format('d M Y, h:i A') }}
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
