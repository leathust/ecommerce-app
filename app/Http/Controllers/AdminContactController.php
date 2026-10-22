<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminContactIndexRequest;
use App\Http\Requests\ReplyContactRequest;
use App\Http\Requests\UpdateContactStatusRequest;
use App\Mail\ContactReplyMail;
use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class AdminContactController extends Controller
{
    public function index(AdminContactIndexRequest $request): View
    {
        $filters = $request->validated();

        $contacts = Contact::query()
            ->search($filters['q'] ?? null)
            ->status($filters['status'] ?? null)
            ->reason($filters['reason'] ?? null)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.contacts.index', [
            'contacts' => $contacts,
            'statuses' => Contact::STATUSES,
            'reasons' => Contact::REASONS,
            'filters' => $filters,
        ]);
    }

    public function updateStatus(UpdateContactStatusRequest $request, Contact $contact): RedirectResponse
    {
        $contact->update($request->validated());

        return back()->with('success', 'Đã cập nhật trạng thái liên hệ.');
    }

    public function reply(ReplyContactRequest $request, Contact $contact): RedirectResponse
    {
        if (! filter_var($contact->email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['reply_message' => 'Liên hệ này không có email hợp lệ.']);
        }

        $data = $request->validated();

        try {
            Mail::to($contact->email)->send(new ContactReplyMail(
                $contact->full_name,
                $data['subject'],
                $data['reply_message'],
            ));
        } catch (TransportExceptionInterface $exception) {
            report($exception);

            return back()->withInput()->withErrors([
                'reply_message' => 'Chưa gửi được email. Vui lòng kiểm tra cấu hình mail hoặc thử lại sau.',
            ]);
        }

        return back()->with('success', 'Đã gửi phản hồi qua hệ thống mail đến '.$contact->email.'.');
    }
}
