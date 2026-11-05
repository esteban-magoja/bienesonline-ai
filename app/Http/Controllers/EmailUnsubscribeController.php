<?php

namespace App\Http\Controllers;

use App\Models\EmailSendRecipient;
use App\Models\EmailSuppression;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * Baja de los correos enviados desde el admin. El GET solo muestra la confirmación (los
 * antivirus de correo abren los links); la baja se hace por POST.
 */
class EmailUnsubscribeController extends Controller
{
    public function show(EmailSendRecipient $recipient): View
    {
        return view('email-unsubscribe', [
            'email' => $recipient->email,
            'unsubscribed' => EmailSuppression::isSuppressed($recipient->email),
            'formUrl' => URL::signedRoute('email.unsubscribe.store', ['recipient' => $recipient->id]),
        ]);
    }

    public function store(Request $request, EmailSendRecipient $recipient): View|Response
    {
        EmailSuppression::suppress($recipient->email);

        // One-click desde el cliente de correo: no necesita página
        if ($request->input('List-Unsubscribe') === 'One-Click') {
            return response()->noContent();
        }

        return view('email-unsubscribe', [
            'email' => $recipient->email,
            'unsubscribed' => true,
            'formUrl' => null,
        ]);
    }
}
