<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Email;
use Livewire\WithPagination;

class EmailInbox extends Component
{
    use WithPagination;

    public $search = '';
    public $selectedEmail = null;

    public function view($id)
    {
        $this->selectedEmail = Email::find($id);
    }

    public function render()
    {
        $emails = Email::when($this->search, function ($query) {
            $query->where('subject', 'like', '%' . $this->search . '%')
                ->orWhere('from', 'like', '%' . $this->search . '%');
        })->latest('received_at')->paginate(10);

        return view('livewire.email-inbox', compact('emails'));
    }
}
