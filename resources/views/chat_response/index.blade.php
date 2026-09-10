@extends('app')

@section('content')
    @include('templates.blockheader', ['pagename' => 'Chat Responses'])

    <div class="row clearfix">
        <div class="col-md-12">
            <div class="card">
                <div class="header">
                    <h2><strong>AI Chat Responses</strong></h2>
                </div>
                <div class="body">
                    @if ($auditUnavailable)
                        <div class="alert alert-warning mb-0">
                            Chat response records are not available yet. Please contact the administrator to apply the audit migration.
                        </div>
                    @elseif ($responses->isEmpty())
                        <div class="alert alert-info mb-0">No AI chat responses have been recorded for this company.</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Question</th>
                                        <th>Response</th>
                                        <th>Response Payload</th>
                                        <th>Timing</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($responses as $chat)
                                        @php
                                            $payload = json_decode($chat->response_payload, true);
                                            $formattedPayload = json_last_error() === JSON_ERROR_NONE
                                                ? json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                                                : $chat->response_payload;
                                        @endphp
                                        <tr>
                                            <td>{{ $chat->username ?: '—' }}</td>
                                            <td style="min-width: 200px; white-space: pre-wrap;">{{ $chat->question ?: '—' }}</td>
                                            <td style="min-width: 220px; white-space: pre-wrap;">{{ $chat->response ?: '—' }}</td>
                                            <td style="min-width: 240px;">
                                                @if ($formattedPayload)
                                                    <details class="chat-response-payload" style="color: #ffffff;">
                                                        <summary style="color: #ffffff; cursor: pointer;">View payload</summary>
                                                        <pre class="mb-0 mt-2" style="white-space: pre-wrap; max-width: 420px; color: #ffffff; background: transparent;">{{ $formattedPayload }}</pre>
                                                    </details>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td style="white-space: nowrap;">
                                                <div>{{ $chat->searched_at ? \Carbon\Carbon::parse($chat->searched_at)->format('d M Y, h:i:s A') : '—' }}</div>
                                                @if (!is_null($chat->response_time_ms))
                                                    <small class="text-muted">{{ number_format($chat->response_time_ms) }} ms</small>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-end">
                            {{ $responses->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
