<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequest;
use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function create(): View
    {
        return view('frontend.contact', [
            'reasons' => Contact::REASONS,
        ]);
    }

    public function store(StoreContactRequest $request): RedirectResponse
    {
        Contact::create($request->safe()->except('website'));

        return redirect()
            ->route('contact')
            ->with('success', 'Cảm ơn bạn đã liên hệ. Chúng tôi sẽ phản hồi trong thời gian sớm nhất.');
    }
}
