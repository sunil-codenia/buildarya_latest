<div class="col-lg-12 col-md-12 col-sm-12">
    <div class="card">
        <div class="body">
            <form action="{{ url('/dashboard') }}" method="GET" id="dashboard_filter_form">
                <div class="row clearfix align-items-end">
                    <div class="col-lg-2 col-md-4 col-sm-6">
                        <label>Date Filter</label>
                        <select class="form-control" name="date_filter" id="date_filter" onchange="toggleCustomDate()">
                            <option value="today" {{ $filter_type == 'today' ? 'selected' : '' }}>Today</option>
                            <option value="this_week" {{ $filter_type == 'this_week' ? 'selected' : '' }}>This Week</option>
                            <option value="this_month" {{ $filter_type == 'this_month' ? 'selected' : '' }}>This Month</option>
                            <option value="this_year" {{ $filter_type == 'this_year' ? 'selected' : '' }}>This Year</option>
                            <option value="all_time" {{ $filter_type == 'all_time' ? 'selected' : '' }}>All Time</option>
                            <option value="custom" {{ $filter_type == 'custom' ? 'selected' : '' }}>Custom Range</option>
                        </select>
                    </div>

                    <div class="col-lg-2 col-md-4 col-sm-6 custom-date-fields" style="display: {{ $filter_type == 'custom' ? 'block' : 'none' }}">
                        <label>From</label>
                        <input type="date" name="from_date" class="form-control" value="{{ $from_date }}">
                    </div>

                    <div class="col-lg-2 col-md-4 col-sm-6 custom-date-fields" style="display: {{ $filter_type == 'custom' ? 'block' : 'none' }}">
                        <label>To</label>
                        <input type="date" name="to_date" class="form-control" value="{{ $to_date }}">
                    </div>

                    @php
                        $role_id = session()->get('role');
                        $role_details = getRoleDetailsById($role_id);
                        $visiblity_at_site = $role_details->visiblity_at_site;
                        $assigned_ids = session()->get('assigned_site_ids', []);
                    @endphp

                    @if(isset($sitesnameadd))
                    <div class="col-lg-2 col-md-4 col-sm-6">
                        <label>Site Filter</label>
                        <select class="form-control" name="site_id" id="site_id">
                            @if($visiblity_at_site != 'current')
                                <option value="all">All Sites</option>
                            @else
                                @if(count($assigned_ids) > 1)
                                    <option value="all" {{ (isset($id) && $id == 'all') ? 'selected' : '' }}>All Assigned Sites</option>
                                @endif
                            @endif
                            @foreach($sitesnameadd as $site)
                                <option value="{{ $site->id }}" {{ (isset($id) && $id == $site->id) ? 'selected' : '' }}>{{ $site->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif

                    <div class="col-lg-2 col-md-4 col-sm-6">
                        <div class="checkbox">
                            <input id="enable_comparison" type="checkbox" name="enable_comparison" {{ isset($compare_site_id) ? 'checked' : '' }} onchange="toggleComparison()">
                            <label for="enable_comparison">Compare Site</label>
                        </div>
                    </div>

                    <div id="comparison_site_div" class="col-lg-2 col-md-4 col-sm-6" style="display: {{ isset($compare_site_id) ? 'block' : 'none' }}">
                        <label>Compare With</label>
                        <select class="form-control" name="compare_site_id">
                            <option value="">Select Site</option>
                            @foreach($sitesnameadd ?? [] as $site)
                                <option value="{{ $site->id }}" {{ (isset($compare_site_id) && $compare_site_id == $site->id) ? 'selected' : '' }}>{{ $site->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-lg-2 col-md-4 col-sm-6 d-flex align-items-end" style="justify-content:space-between; gap:5px;">
                        <button type="submit" class="btn btn-info" style="font-size:smaller; flex:1; white-space: nowrap;">Filter</button>
                        <button type="button" onclick="switchSiteNow()" class="btn btn-warning" title="Switch as Active Site" style="font-size:smaller; flex:0 0 auto; white-space: nowrap;"><i class="zmdi zmdi-apps"></i> Switch</button>
                        <button type="submit" formaction="{{ url('/dashboard/export') }}" class="btn btn-success" style="font-size:smaller; flex:1; white-space: nowrap;">Export CSV</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function toggleCustomDate() {
        const type = document.getElementById('date_filter').value;
        const fields = document.querySelectorAll('.custom-date-fields');
        fields.forEach(f => f.style.display = (type === 'custom' ? 'block' : 'none'));
    }

    function toggleComparison() {
        const checked = document.getElementById('enable_comparison').checked;
        document.getElementById('comparison_site_div').style.display = (checked ? 'block' : 'none');
    }

    function switchSiteNow() {
        var sid = document.getElementById('site_id').value;
        if (sid) {
            window.location.href = "{{ url('/switch_site') }}/" + btoa(sid);
        } else {
            alert('Please select a site first');
        }
    }
</script>
