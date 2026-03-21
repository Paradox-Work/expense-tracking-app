<?php

use App\Models\Expense;
use Livewire\Component;
use App\Models\Category;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;

new #[Title('Expenses')] class extends Component
{




    public $expenseId;
    // #[Validate('required|numeric|min:0.01')]
    public $amount = '';
    public $title = '';
    public $description = '';
    public $date;
    public $category_id = '';
    public $type = 'one-time';
    public $recurring_frequency = 'monthly';
    public $recurring_start_date;
    public $recurring_end_date; 
    
    public $isEdit = false;

    protected function rules()
    {
        $rules = [
            'amount' => 'required|numeric|min:0.01',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'date' => 'required|date',
            'category_id' => 'nullable|exists:categories,id',
            'type' => 'required|in:one-time,recurring',
        ];

        if ($this->type === 'recurring') {
            $rules['recurring_frequency'] = 'required|in:daily,weekly,monthly,yearly';
            $rules['recurring_start_date'] = 'required|date';
            $rules['recurring_end_date'] = 'nullable|date|after:recurring_start_date';
        }

        return $rules;
    }

    public function mount($expenseId = null){
       if($expenseId){
        $this->isEdit = true;
        $this->expenseId = $expenseId;
        $this->loadExpense();
       }else{
        $this->date = now()->format('Y-m-d');
        $this->recurring_start_date = now()->format('Y-m-d');
       }
    }

    public function loadExpense(){
         $expense = Expense::findOrFail($this->expenseId);
        
        if ($expense->user_id !== Auth::user()->id) {
            abort(403);
        }
        $this->amount = $expense->amount;
        $this->title = $expense->title;
        $this->description = $expense->description;
        $this->date = $expense->date->format('Y-m-d');
        $this->category_id = $expense->category_id;
        $this->type = $expense->type;
        $this->recurring_frequency = $expense->recurring_frequency;
        $this->recurring_start_date = $expense->recurring_start_date?->format('Y-m-d');
        $this->recurring_end_date = $expense->recurring_end_date?->format('Y-m-d');
    }

    #[Computed]
    public function categories(){
        return Category::where('user_id',Auth::user()->id)
        ->orderBy('name','asc')
        ->get();
    }

    public function save(){
        $this->validate();
        $data = [
            'user_id' => Auth::user()->id,
            'amount' => $this->amount,
            'title' => $this->title,
            'description' => $this->description,
            'date' => $this->date,
            'category_id' => $this->category_id ?: null,
            'type' => $this->type,
        ];
        if ($this->type === 'recurring') {
            $data['recurring_frequency'] = $this->recurring_frequency;
            $data['recurring_start_date'] = $this->recurring_start_date;
            $data['recurring_end_date'] = $this->recurring_end_date ?: null;
        } else {
            $data['recurring_frequency'] = null;
            $data['recurring_start_date'] = null;
            $data['recurring_end_date'] = null;
        }

        if($this->isEdit){
            $expense = Expense::findOrFail($this->expenseId);
            if ($expense->user_id !== Auth::user()->id) {
                abort(403);
            }
            $expense->update($data);
            session()->flash('message', 'Expense updated successfully.');
        }else{
            Expense::create($data);
            session()->flash('message', 'Expense created successfully.');
        }

        return redirect()->route('expenses.index');

    }
    
     public function render()
     {        
         return $this->view()->title($this->isEdit ? 'Edit Expense' : 'Add New Expense');
     }
};
?>

<div>
    {{-- Very little is needed to make a happy life. - Marcus Aurelius --}}
    <div class="min-h-screen bg-gray-50 dark:bg-neutral-800">
    <!-- Header -->
    <div class=" shadow-sm border-b border-gray-200 bg-gradient-to-r border-amber-600 from-orange-500 to-red-700">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-white">
                        {{ $isEdit ? 'Edit Expense' : 'Add New Expense' }}
                    </h1>
                    <p class="text-indigo-100 mt-1">{{ $isEdit ? 'Update expense details' : 'Record a new expense' }}</p>
                </div>
                <a href="/expenses" class="text-gray-600 hover:text-gray-900 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </a>
            </div>
        </div>
    </div>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <form wire:submit="save" class="space-y-6">

            <!-- Basic Information Card -->
            <div class="bg-white rounded-xl shadow-md p-6 dark:bg-neutral-700">
                <h3 class="text-lg font-semibold text-gray-800 mb-6 dark:text-gray-200">Basic Information</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Amount -->
                    <div>
                        <label for="amount" class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-200">
                            Amount <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="text-gray-500 text-lg">$</span>
                            </div>
                            <input type="number" id="amount" wire:model="amount" step="0.01" min="0" placeholder="0.00"
                                class="w-full pl-8 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-400 focus:border-transparent @error('amount') border-red-500 @enderror">
                        </div>
                        @error('amount')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Date -->
                    <div>
                        <label for="date" class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-200">
                            Date <span class="text-red-500">*</span>
                        </label>
                        <input type="date" id="date" wire:model="date"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-400 focus:border-transparent @error('date') border-red-500 @enderror">
                        @error('date')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Title -->
                    <div class="md:col-span-2">
                        <label for="title" class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-200">
                            Title <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="title" wire:model="title" placeholder="e.g., Grocery Shopping"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-400 focus:border-transparent @error('title') border-red-500 @enderror">
                        @error('title')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Category -->
                    <div class="md:col-span-2">
                        <label for="category_id" class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-200">
                            Category
                        </label>
                        <select wire:model="category_id" id="category_id"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-400 focus:border-transparent dark:bg-neutral-700 dark:text-gray-200">
                            <option value="">Select a category</option>
                            @foreach($this->categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Don't see your category? <a href="/categories"
                                class="text-amber-600 hover:text-amber-400 font-medium">Create one</a>
                        </p>
                    </div>

                    <!-- Description -->
                    <div class="md:col-span-2">
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-200">
                            Description
                        </label>
                        <textarea wire:model="description" id="description" rows="3"
                            placeholder="Add any additional notes..."
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-400 focus:border-transparent resize-none"></textarea>
                    </div>
                </div>
            </div>

            <!-- Expense Type Card -->
            <div class="bg-white rounded-xl shadow-md p-6 dark:bg-neutral-700">
                <h3 class="text-lg font-semibold text-gray-800 mb-6 dark:text-gray-200">Expense Type</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <label
                        class="relative flex items-center p-4 border-2 rounded-lg cursor-pointer transition {{ $type === 'one-time' ? 'border-amber-400 bg-purple-50 dark:bg-gray-300' : 'dark:bg-gray-200 border-gray-400 hover:border-gray-300' }}">
                        <input type="radio" wire:model.live="type" value="one-time" class="sr-only">
                        <div class="flex-1">
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-10 h-10 rounded-lg {{ $type === 'one-time' ? 'bg-amber-400' : 'bg-gray-300' }} flex items-center justify-center">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="font-semibold text-gray-900">One-time</div>
                                    <div class="text-sm text-gray-600">Single expense</div>
                                </div>
                            </div>
                        </div>
                        @if($type === 'one-time')
                            <svg class="w-6 h-6 text-amber-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                    clip-rule="evenodd" />
                            </svg>
                        @endif
                    </label>

                    <label
                        class="relative flex items-center p-4 border-2 rounded-lg cursor-pointer transition {{ $type === 'recurring' ? 'border-amber-400 bg-purple-50 dark:bg-gray-300' : 'dark:bg-gray-200 border-gray-400 hover:border-gray-300' }}">
                        <input type="radio" wire:model.live="type" value="recurring" class="sr-only">
                        <div class="flex-1">
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-10 h-10 rounded-lg {{ $type === 'recurring' ? 'bg-amber-400' : 'bg-gray-300' }} flex items-center justify-center">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="font-semibold text-gray-900">Recurring</div>
                                    <div class="text-sm text-gray-600">Repeating expense</div>
                                </div>
                            </div>
                        </div>
                        @if($type === 'recurring')
                            <svg class="w-6 h-6 text-amber-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                    clip-rule="evenodd" />
                            </svg>
                        @endif
                    </label>
                </div>

                @if($type === 'recurring')
                    <div class="space-y-4 p-4 bg-purple-50 rounded-lg border border-amber-200 dark:bg-gray-300">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <!-- Frequency -->
                            <div>
                                <label for="recurring_frequency" class="block text-sm font-medium text-gray-700 mb-2">
                                    Frequency <span class="text-red-500">*</span>
                                </label>
                                <select wire:model="recurring_frequency" id="recurring_frequency"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent @error('recurring_frequency') border-red-500 @enderror dark:border-gray-500 dark:text-gray-700">
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                    <option value="yearly">Yearly</option>
                                </select>
                                @error('recurring_frequency')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Start Date -->
                            <div>
                                <label for="recurring_start_date" class="block text-sm font-medium text-gray-700 mb-2">
                                    Start Date <span class="text-red-500">*</span>
                                </label>
                                <input type="date" id="recurring_start_date" wire:model="recurring_start_date"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent @error('recurring_start_date') border-red-500 @enderror dark:border-gray-500 dark:text-gray-700">
                                @error('recurring_start_date')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- End Date -->
                            <div>
                                <label for="recurring_end_date" class="block text-sm font-medium text-gray-700 mb-2">
                                    End Date <span class="text-gray-500">(Optional)</span>
                                </label>
                                <input type="date" id="recurring_end_date" wire:model="recurring_end_date"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent @error('recurring_end_date') border-red-500 @enderror dark:border-gray-500 dark:text-gray-700">
                                @error('recurring_end_date')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="flex items-start gap-2 text-sm text-amber-600">
                            <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                    clip-rule="evenodd" />
                            </svg>
                            <div>
                                <strong>Note:</strong> This expense will automatically generate new entries based on the
                                frequency.
                                The scheduler runs daily at midnight to create new occurrences.
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Form Actions -->
            <div class="flex items-center justify-between">
                <a href="/expenses"
                    class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 font-semibold hover:bg-gray-50 transition dark:border-gray-500 dark:text-gray-200 dark:hover:bg-gray-400">
                    Cancel
                </a>
                <button type="submit"
                    class="px-8 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-lg font-semibold hover:shadow-lg transition transform hover:-translate-y-0.5 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    {{ $isEdit ? 'Update Expense' : 'Save Expense' }}
                </button>
            </div>

        </form>

    </div>
</div>
</div>