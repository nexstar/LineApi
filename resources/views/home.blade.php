@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">Dashboard</div>

                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif

                    You are logged in!
                </div>
            </div>
            <p>Send push message</p>
            @foreach($botUserProfiles as $botUserProfile)
                <a class="btn btn-outline-dark btn-block" href="{{ url('line/spm', [$botUserProfile->id]) }}" role="button">Send push message to {{ $botUserProfile->display_name }}</a>
            @endforeach

            <!-- Line Pay API -->
            <p>付款 Line Pay</p>
            <a class="btn btn-outline-dark btn-block" href="{{ url('line/reserveapi') }}" role="button">付款</a>
            <table class="table">
                <thead>
                     <tr>
                         <th scope="col">transactionId</th>
                         <th scope="col">狀態</th>
                         <th scope="col">查看紀錄</th>
                         <th scope="col">退款</th>
                         <th scope="col">自動付款</th>
                     </tr>
                </thead>
                <tbody>
                @foreach($orders as $order)
                    <tr>
                        <th scope="row">{{ $order->transactionId }}</th>
                        <th scope="row">{{ $order->status }}</th>
                        <td><a class="btn btn-outline-dark btn-block" href="{{ url('line/paymentsapi', [$order->transactionId]) }}" role="button">查看</a></td>
                        <td><a class="btn btn-outline-dark btn-block" href="{{ url('line/refundapi', [$order->transactionId]) }}" role="button">退款</a></td>
                        <td><a class="btn btn-outline-dark btn-block" href="{{ url('line/regkeyapi', [$order->orderId]) }}" role="button">自動付款</a></td>
                     </tr>
                @endforeach
                </tbody>
            </table>
            @if (session('payments'))
                <div class="alert alert-success">
                    {{ session('payments') }}
                </div>
            @endif
            @if (session('refund'))
                <div class="alert alert-success">
                    {{ session('refund') }}
                </div>
            @endif
            @if (session('regkey'))
                <div class="alert alert-success">
                    {{ session('regkey') }}
                </div>
            @endif
            <!-- Line Pay API -->
        </div>
    </div>
</div>
@endsection
