<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequest;
use App\Mail\ContactSubmissionMail;
use App\Models\Contact;
use App\Support\SystemMailMetadata;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

class ContactController extends Controller
{
    public function create(): View
    {
        return view('frontend.contact', [
            'reasons' => Contact::REASONS,
        ]);
    }

    public function store(StoreContactRequest $request, SystemMailMetadata $mailMetadata): RedirectResponse
    {
        $contact = Contact::create($request->safe()->except('website'));
        $recipient = $mailMetadata->contactRecipient();

        if ($recipient !== null) {
            try {
                Mail::to($recipient)->send(new ContactSubmissionMail(
                    $contact,
                    $mailMetadata->senderName(),
                ));
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return redirect()
            ->route('contact')
            ->with('success', 'Cảm ơn bạn đã liên hệ. Chúng tôi sẽ phản hồi trong thời gian sớm nhất.');
    }
}
