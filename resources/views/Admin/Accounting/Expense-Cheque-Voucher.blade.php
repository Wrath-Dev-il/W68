@section('expense_cheque_voucher_content')
@include('partials.accounting.expense-cheque-voucher-content')
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/expense_cheque_voucher.css') }}?v={{ time() }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/expense_cheque_voucher.js') }}?v={{ time() }}"></script>
@endpush

@include('partials.admin.admin_sidebar_navbar')
