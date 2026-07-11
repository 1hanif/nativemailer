<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Email;
use Livewire\Attributes\On;
use Livewire\WithPagination;
use Native\Desktop\Events\ChildProcess\MessageReceived;

class EmailInbox extends Component
{
    use WithPagination;

    public $search = '';
    public $selectedEmail = null;

    /**
     * Re-render as soon as the SMTP catcher reports a new email.
     * The catcher's ChildProcess::message() triggers MessageReceived,
     * which NativePHP broadcasts to the frontend on the 'nativephp' channel.
     */
    #[On('native:' . MessageReceived::class)]
    public function onEmailReceived()
    {
        // Empty on purpose: receiving the event triggers a re-render.
    }

    public function view($id)
    {
        $this->selectedEmail = Email::find($id);
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function render()
    {
        $emails = Email::when($this->search, function ($query) {
            $query->where(function ($q) {
                $q->where('subject', 'like', '%' . $this->search . '%')
                    ->orWhere('from', 'like', '%' . $this->search . '%');
            });
        })->latest('received_at')->paginate(10);

        return view('livewire.email-inbox', compact('emails'));
    }
}
