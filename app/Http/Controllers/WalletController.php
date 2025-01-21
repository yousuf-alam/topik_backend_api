<?php

namespace App\Http\Controllers;

use App\Models\User\Wallet;
use App\Models\User\WalletHistory;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function creditWallet(Request $request)
    {
        $user = $request->user();
        $type = $request->type;
        $walletType = $request->wallet_type;
        $amount = $request->amount;

// Fetch wallet
        $wallet = Wallet::where('user_id', $user->id)->first();

// Update wallet balance
        if ($walletType === 'coins') {
            $wallet->coins += $amount;
        } elseif ($walletType === 'gems') {
            $wallet->gems += $amount;
        }
        $wallet->save();

// Create wallet history entry
        $walletHistory = new WalletHistory();
        $walletHistory->user_id = $user->id;
        $walletHistory->type = $type;
        $walletHistory->description = "You have got $amount for $type.";

        if ($walletType === 'coins') {
            $walletHistory->credit_coins = $amount;
            $walletHistory->coin_balance = $wallet->coins;
        } elseif ($walletType === 'gems') {
            $walletHistory->credit_gems = $amount;
            $walletHistory->gems_balance = $wallet->gems;
        }

        $walletHistory->save();

        $user['wallet']=$user->wallet;

// Return response
        return response()->json([
            'success' => true,
            'message' => 'Your wallet has been credited.',
            'data' => $user,
        ]);

    }
    public function debitWallet(Request $request)
    {
        $user = $request->user();
        $type = $request->type;
        $walletType = $request->wallet_type;
        $amount = $request->amount;

// Fetch wallet
        $wallet = Wallet::where('user_id', $user->id)->first();

// Update wallet balance
        if ($walletType === 'coins') {
            $wallet->coins -= $amount;
        } elseif ($walletType === 'gems') {
            $wallet->gems -= $amount;
        }
        $wallet->save();
        $walletHistory = new WalletHistory();
        $walletHistory->user_id = $user->id;
        $walletHistory->type = $type;
        $walletHistory->description = "You have got $amount for $type.";

        if ($walletType === 'coins') {
            $walletHistory->debit_coins = $amount;
            $walletHistory->coin_balance = $wallet->coins;
        } elseif ($walletType === 'gems') {
            $walletHistory->debit_gems = $amount;
            $walletHistory->gems_balance = $wallet->gems;
        }

        $walletHistory->save();
        $user['wallet']=$user->wallet;

// Return response
        return response()->json([
            'success' => true,
            'message' => 'Your wallet has been debited.',
            'data' => $user,
        ]);

    }
}
