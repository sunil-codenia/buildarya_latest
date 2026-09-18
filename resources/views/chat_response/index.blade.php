@extends('app')

@section('content')
    @include('templates.blockheader', ['pagename' => 'Chat Responses'])

    <div class="row clearfix">
        <div class="col-md-12">
            <div class="card">
                <div class="header d-flex justify-content-between align-items-center">
                    <h2><strong>AI Chat Responses</strong></h2>
                    <div id="bulk-action-bar" style="display: none;">
                        <button type="button" class="btn btn-sm btn-success waves-effect" onclick="bulkSetChecked(1)">
                            <i class="zmdi zmdi-check"></i> Mark Selected Checked
                        </button>
                        <button type="button" class="btn btn-sm btn-secondary waves-effect ml-1" onclick="bulkSetChecked(0)">
                            <i class="zmdi zmdi-close"></i> Mark Selected Unchecked
                        </button>
                    </div>
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
                            <table class="table table-bordered table-striped table-hover mb-0" id="chat-responses-table">
                                <thead>
                                    <tr>
                                        <th style="width: 60px; text-align: center; vertical-align: middle;">S.No</th>
                                        <th style="vertical-align: middle;">Username</th>
                                        <th style="vertical-align: middle;">Question</th>
                                        <th style="vertical-align: middle;">Response</th>
                                        <th style="vertical-align: middle;">Response Payload</th>
                                        <th style="width: 110px; text-align: center; vertical-align: middle;">
                                            <div>Checked</div>
                                            <div style="font-size: 11px; font-weight: normal; margin-top: 2px;">
                                                <label style="cursor: pointer; margin-bottom: 0; color: #9ca3af;" title="Select / Deselect all on this page">
                                                    <input type="checkbox" id="check-all-responses" style="accent-color: #10b981; vertical-align: middle; cursor: pointer;"> All
                                                </label>
                                            </div>
                                        </th>
                                        <th style="vertical-align: middle;">Timing</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($responses as $chat)
                                        @php
                                            $payload = json_decode($chat->response_payload, true);
                                            $formattedPayload = json_last_error() === JSON_ERROR_NONE
                                                ? json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                                                : $chat->response_payload;
                                            $sno = $responses->firstItem() ? ($responses->firstItem() + $loop->index) : $loop->iteration;
                                            $isChecked = !empty($chat->is_checked);
                                        @endphp
                                        <tr id="row-{{ $chat->id }}">
                                            <td style="text-align: center; font-weight: 600; vertical-align: middle;">{{ $sno }}</td>
                                            <td style="vertical-align: middle;">{{ $chat->username ?: '—' }}</td>
                                            <td style="min-width: 200px; white-space: pre-wrap; vertical-align: middle;">{{ $chat->question ?: '—' }}</td>
                                            <td style="min-width: 220px; white-space: pre-wrap; vertical-align: middle;">{{ $chat->response ?: '—' }}</td>
                                            <td style="min-width: 240px; vertical-align: middle;">
                                                @if ($formattedPayload)
                                                    <details class="chat-response-payload" style="color: #ffffff;">
                                                        <summary style="color: #ffffff; cursor: pointer;">View payload</summary>
                                                        <pre class="mb-0 mt-2" style="white-space: pre-wrap; max-width: 420px; color: #ffffff; background: transparent;">{{ $formattedPayload }}</pre>
                                                    </details>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td style="text-align: center; vertical-align: middle;">
                                                <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px;">
                                                    <input type="checkbox" 
                                                           class="chat-response-checkbox" 
                                                           data-id="{{ $chat->id }}" 
                                                           {{ $isChecked ? 'checked' : '' }} 
                                                           style="width: 18px; height: 18px; cursor: pointer; accent-color: #10b981;">
                                                    <span class="status-indicator" id="status-indicator-{{ $chat->id }}" style="font-size: 11px; font-weight: 600; color: {{ $isChecked ? '#10b981' : '#9ca3af' }};">
                                                        {{ $isChecked ? 'Checked' : 'Unchecked' }}
                                                    </span>
                                                </div>
                                            </td>
                                            <td style="white-space: nowrap; vertical-align: middle;">
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

                        <div class="d-flex justify-content-end mt-3">
                            {{ $responses->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const checkboxes = document.querySelectorAll('.chat-response-checkbox');
        const checkAll = document.getElementById('check-all-responses');
        const bulkActionBar = document.getElementById('bulk-action-bar');

        function updateBulkBarVisibility() {
            if (!bulkActionBar) return;
            const anyChecked = Array.from(checkboxes).some(cb => cb.checked);
            bulkActionBar.style.display = anyChecked ? 'block' : 'none';
        }

        checkboxes.forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                const id = this.getAttribute('data-id');
                const isChecked = this.checked ? 1 : 0;
                const indicator = document.getElementById('status-indicator-' + id);

                if (indicator) {
                    indicator.textContent = 'Updating...';
                    indicator.style.color = '#eab308';
                }

                fetch('{{ route("chat.response.updateCheck") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        id: id,
                        is_checked: isChecked
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        if (indicator) {
                            indicator.textContent = isChecked ? 'Checked' : 'Unchecked';
                            indicator.style.color = isChecked ? '#10b981' : '#9ca3af';
                        }
                        showToast(data.message || 'Status updated', 'success');
                    } else {
                        checkbox.checked = !isChecked;
                        if (indicator) {
                            indicator.textContent = !isChecked ? 'Checked' : 'Unchecked';
                            indicator.style.color = !isChecked ? '#10b981' : '#9ca3af';
                        }
                        showToast(data.message || 'Update failed', 'error');
                    }
                    updateBulkBarVisibility();
                })
                .catch(err => {
                    console.error('Error:', err);
                    checkbox.checked = !isChecked;
                    if (indicator) {
                        indicator.textContent = !isChecked ? 'Checked' : 'Unchecked';
                        indicator.style.color = !isChecked ? '#10b981' : '#9ca3af';
                    }
                    showToast('Network error while saving', 'error');
                    updateBulkBarVisibility();
                });
            });
        });

        if (checkAll) {
            checkAll.addEventListener('change', function() {
                const targetState = this.checked;
                const ids = [];
                checkboxes.forEach(cb => {
                    ids.push(cb.getAttribute('data-id'));
                });

                if (ids.length === 0) return;

                checkboxes.forEach(cb => {
                    cb.checked = targetState;
                    const ind = document.getElementById('status-indicator-' + cb.getAttribute('data-id'));
                    if (ind) {
                        ind.textContent = 'Updating...';
                        ind.style.color = '#eab308';
                    }
                });

                fetch('{{ route("chat.response.bulkUpdateCheck") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        ids: ids,
                        is_checked: targetState ? 1 : 0
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        checkboxes.forEach(cb => {
                            const ind = document.getElementById('status-indicator-' + cb.getAttribute('data-id'));
                            if (ind) {
                                ind.textContent = targetState ? 'Checked' : 'Unchecked';
                                ind.style.color = targetState ? '#10b981' : '#9ca3af';
                            }
                        });
                        showToast(data.message || 'All records updated', 'success');
                    } else {
                        showToast(data.message || 'Bulk update failed', 'error');
                    }
                    updateBulkBarVisibility();
                })
                .catch(err => {
                    console.error('Bulk error:', err);
                    showToast('Network error on bulk update', 'error');
                    updateBulkBarVisibility();
                });
            });
        }

        window.bulkSetChecked = function(state) {
            const checkedBoxes = Array.from(checkboxes).filter(cb => cb.checked);
            const ids = checkedBoxes.map(cb => cb.getAttribute('data-id'));
            if (ids.length === 0) {
                showToast('Please select at least one record', 'warning');
                return;
            }

            fetch('{{ route("chat.response.bulkUpdateCheck") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    ids: ids,
                    is_checked: state ? 1 : 0
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    checkedBoxes.forEach(cb => {
                        cb.checked = Boolean(state);
                        const ind = document.getElementById('status-indicator-' + cb.getAttribute('data-id'));
                        if (ind) {
                            ind.textContent = state ? 'Checked' : 'Unchecked';
                            ind.style.color = state ? '#10b981' : '#9ca3af';
                        }
                    });
                    showToast(data.message || 'Updated', 'success');
                    updateBulkBarVisibility();
                }
            });
        };

        function showToast(msg, type) {
            let toast = document.getElementById('chat-response-floating-toast');
            if (!toast) {
                toast = document.createElement('div');
                toast.id = 'chat-response-floating-toast';
                toast.style.position = 'fixed';
                toast.style.bottom = '24px';
                toast.style.right = '24px';
                toast.style.zIndex = '999999';
                toast.style.padding = '12px 20px';
                toast.style.borderRadius = '8px';
                toast.style.color = '#ffffff';
                toast.style.fontSize = '13.5px';
                toast.style.fontWeight = '600';
                toast.style.boxShadow = '0 6px 18px rgba(0,0,0,0.35)';
                toast.style.transition = 'opacity 0.25s ease, transform 0.25s ease';
                document.body.appendChild(toast);
            }
            toast.style.backgroundColor = type === 'success' ? '#10b981' : (type === 'warning' ? '#f59e0b' : '#ef4444');
            toast.textContent = msg;
            toast.style.display = 'block';
            toast.style.opacity = '1';
            toast.style.transform = 'translateY(0)';
            clearTimeout(window._chatToastTimer);
            window._chatToastTimer = setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(10px)';
                setTimeout(() => { toast.style.display = 'none'; }, 250);
            }, 2500);
        }
    });
    </script>
@endsection
