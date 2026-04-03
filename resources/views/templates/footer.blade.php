@yield('chart_script');


<script src="{{ asset('/js/pages/index.js') }}"></script>
<script src="/plugins/DataTables/datatables.min.js"></script>
<script src="/js/select2.js"></script>
<script>
    $(document).ready(function() {
        $('.select2').select2({
            delay: 250,
            cache: true
        });
       
            $('.dashboardTable').dataTable({
             
                responsive: true,
               
                "oLanguage": {
                    "oPaginate": {
                        "sFirst": '<i class="zmdi zmdi-fast-rewind"></i>',
                        "sLast": '<i class="zmdi zmdi-fast-forward"></i>',
                        "sPrevious": '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-arrow-left"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>',
                        "sNext": '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-arrow-right"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>'
                    },
                    "sInfo": "Showing ( <b>_START_ - _END_ </b>) Of <b> _TOTAL_ </b> Entries <br> Page<b> _PAGE_ </b>of <b>_PAGES_</b> Pages",
                    "sSearch": '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-search"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>',
                    "sSearchPlaceholder": "Search...",
                    "sLengthMenu": "",
                    "sPadding": '1rem'
                },
                pagingType: "full_numbers",
            });
            $('#dataTable').dataTable({
                initComplete: function() {
                    this.api().columns().every(function() {
                        var column = this;
                        var headertext = column.header().innerHTML;
                        if (headertext != '#' && headertext != 'Action' &&
                            headertext != 'Image') {
                            var search = $('<b>' + headertext +
                                    '</b><br><input class="form-control" type="text" placeholder="Search">'
                                    )
                                .appendTo($(column.header()).empty())
                                .on('change input', function() {
                                    var val = $(this).val()
                                    column
                                        .search(val ? val : '', true, false)
                                        .draw();
                                });
                        }
                    });
                },
                responsive: true,
                dom: 'lBfrtip<"actions">',
                buttons: [

                    {
                        extend: 'csv',
                        text: window.csvButtonTrans,
                        exportOptions: {
                            columns: ':visible'
                        },
                        className: 'btn btn-round waves-effect waves-light btn-custom-color'
                    },
                    {
                        extend: 'excel',
                        text: window.excelButtonTrans,
                        exportOptions: {
                            columns: ':visible'
                        },
                        className: 'btn btn-round waves-effect waves-light btn-custom-color'
                    },
                    {
                        extend: 'pdf',
                        text: window.pdfButtonTrans,
                        exportOptions: {
                            columns: ':visible'
                        },
                        className: 'btn btn-round waves-effect waves-light btn-custom-color'
                    },


                ],
                "oLanguage": {
                    "oPaginate": {
                        "sFirst": '<i class="zmdi zmdi-fast-rewind"></i>',
                        "sLast": '<i class="zmdi zmdi-fast-forward"></i>',
                        "sPrevious": '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-arrow-left"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>',
                        "sNext": '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-arrow-right"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>'
                    },
                    "sInfo": "Showing ( <b>_START_ - _END_ </b>) Of <b> _TOTAL_ </b> Entries <br> Page<b> _PAGE_ </b>of <b>_PAGES_</b> Pages",
                    "sSearch": '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-search"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>',
                    "sSearchPlaceholder": "Search...",
                    "sLengthMenu": "Results :  _MENU_",
                    "sPadding": '2rem'
                },
                pagingType: "full_numbers",
            });
            $('#dataTable2').dataTable({
                initComplete: function() {
                    this.api().columns().every(function() {
                        var column = this;
                        var headertext = column.header().innerHTML;
                        if (headertext != '#' && headertext != 'Action' &&
                            headertext != 'Image') {
                            var search = $('<b>' + headertext +
                                    '</b><br><input class="form-control" type="text" placeholder="Search">'
                                    )
                                .appendTo($(column.header()).empty())
                                .on('change input', function() {
                                    var val = $(this).val()
                                    column
                                        .search(val ? val : '', true, false)
                                        .draw();
                                });
                        }
                    });
                },
                responsive: true,
                dom: 'lBfrtip<"actions">',
                buttons: [

                    {
                        extend: 'csv',
                        text: window.csvButtonTrans,
                        exportOptions: {
                            columns: ':visible'
                        },
                        className: 'btn btn-round waves-effect waves-light btn-custom-color'
                    },
                    {
                        extend: 'excel',
                        text: window.excelButtonTrans,
                        exportOptions: {
                            columns: ':visible'
                        },
                        className: 'btn btn-round waves-effect waves-light btn-custom-color'
                    },
                    {
                        extend: 'pdf',
                        text: window.pdfButtonTrans,
                        exportOptions: {
                            columns: ':visible'
                        },
                        className: 'btn btn-round waves-effect waves-light btn-custom-color'
                    },


                ],
                "oLanguage": {
                    "oPaginate": {
                        "sFirst": '<i class="zmdi zmdi-fast-rewind"></i>',
                        "sLast": '<i class="zmdi zmdi-fast-forward"></i>',
                        "sPrevious": '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-arrow-left"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>',
                        "sNext": '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-arrow-right"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>'
                    },
                    "sInfo": "Showing ( <b>_START_ - _END_ </b>) Of <b> _TOTAL_ </b> Entries <br> Page<b> _PAGE_ </b>of <b>_PAGES_</b> Pages",
                    "sSearch": '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-search"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>',
                    "sSearchPlaceholder": "Search...",
                    "sLengthMenu": "Results :  _MENU_",
                    "sPadding": '2rem'
                },
                pagingType: "full_numbers",
            });
            $('.doc_table').dataTable({
                initComplete: function() {
                    this.api().columns().every(function() {
                        var column = this;
                        var headertext = column.header().innerHTML;
                        if (headertext != '#' && headertext != 'Action' &&
                            headertext != 'Image') {
                            var search = $('<b>' + headertext +
                                    '</b><br><input class="form-control" type="text" placeholder="Search">'
                                    )
                                .appendTo($(column.header()).empty())
                                .on('change input', function() {
                                    var val = $(this).val()
                                    column
                                        .search(val ? val : '', true, false)
                                        .draw();
                                });
                        }
                    });
                },
                responsive: true,
                dom: 'lBfrtip<"actions">',
                buttons: [

                    {
                        extend: 'csv',
                        text: window.csvButtonTrans,
                        exportOptions: {
                            columns: ':visible'
                        },
                        className: 'btn btn-round waves-effect waves-light btn-custom-color'
                    },
                    {
                        extend: 'excel',
                        text: window.excelButtonTrans,
                        exportOptions: {
                            columns: ':visible'
                        },
                        className: 'btn btn-round waves-effect waves-light btn-custom-color'
                    },
                    {
                        extend: 'pdf',
                        text: window.pdfButtonTrans,
                        exportOptions: {
                            columns: ':visible'
                        },
                        className: 'btn btn-round waves-effect waves-light btn-custom-color'
                    },


                ],
                "oLanguage": {
                    "oPaginate": {
                        "sFirst": '<i class="zmdi zmdi-fast-rewind"></i>',
                        "sLast": '<i class="zmdi zmdi-fast-forward"></i>',
                        "sPrevious": '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-arrow-left"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>',
                        "sNext": '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-arrow-right"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>'
                    },
                    "sInfo": "Showing ( <b>_START_ - _END_ </b>) Of <b> _TOTAL_ </b> Entries <br> Page<b> _PAGE_ </b>of <b>_PAGES_</b> Pages",
                    "sSearch": '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-search"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>',
                    "sSearchPlaceholder": "Search...",
                    "sLengthMenu": "Results :  _MENU_",
                    "sPadding": '2rem'
                },
                pagingType: "full_numbers",
            });
        });



</script>

<script src="/plugins/bootstrap-colorpicker/js/bootstrap-colorpicker.js"></script>
<script src="/vendor/sweetalert/sweetalert.all.js"></script>
<script src="/plugins/multi-select/js/jquery.multi-select.js"></script>
<script src="/plugins/jquery-spinner/js/jquery.spinner.js"></script>
<script src="/plugins/bootstrap-tagsinput/bootstrap-tagsinput.js"></script>
<script src="/plugins/nouislider/nouislider.js"></script>
<script src="/plugins/jquery-inputmask/jquery.inputmask.bundle.js"></script>
<script src="/js/pages/forms/advanced-form-elements.js"></script>

<script src="{{ asset('/js/owl.carousel.min.js') }}"></script> <!-- owl crousel  Js -->
