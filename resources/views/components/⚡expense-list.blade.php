<?php

use Livewire\Component;
use App\Models\Expense;
use App\Models\Category;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

new class extends Component
{

    use withPagination;

    public $search = '';

    public $selectedCategory = "";

    public $startDate ="";

    public $endDate = "";

    public $sortBy = "date";

    public $sortDirection = "desc";

    public $showFilters = false;

    public function mount(){
        //default to current month
        if(empty($this->startDate)){
            $this->startDate = now()->startOfMonth()->format("Y-m-d");
        }
        if(empty($this->startDate)){
            $this->startDate = now()->endOfMonth()->format("Y-m-d");
        }
    }

        //sorting
        public function sortBy($field){
            if($this->sortBy === $field){
                $this->sortDirection = $this->sortDirection === "asc" ? "desc" : "asc";
            }else{
                $this->sortBy = $field;
                $this->sortDirection = "asc";
            }
        }

        //delete the expense

        public function deleteExpense($expenseId){
            $expense = Expense::findOrFail($expenseId);
            
            if($expense->user_id !== Auth::user()->id){
                abort(403, "You are not authorized to perform this action.");
            }

            $expense->delete();

            session()->flash('message', 'Expense deleted successfully.');
        }
        //COMPUTED PROPERTY OF EXPENSES
    #[Computed]
    public function expenses(){
        $query = Expense::with('category')
        ->forUser(Auth::user()->id);

        //apply search filter

        if($this->search){
            $query->where('title', 'like', '%'.$this->search.'%')
            ->orWhere('description', 'like', '%'.$this->search.'%');
        }

        if($this->selectedCategory){
            $query->where('category_id', $this->selectedCategory);
        }

        if($this->startDate){
            $query->whereDate('date', '>=', $this->startDate);
        }

        if($this->startDate){
            $query->whereDate('date', '<=', $this->startDate);
        }

        return $query->orderBy($this->sortBy, $this->sortDirection)
        ->paginate(10);

    }

    public function total(){
        $query = Expense::forUser(Auth::user()->id);
        
        if($this->search){
            $query->where('title', 'like', '%'.$this->search.'%')
            ->orWhere('description', 'like', '%'.$this->search.'%');
        }

        if($this->selectedCategory){
            $query->where('category_id', $this->selectedCategory);
        }

        if($this->startDate){
            $query->whereDate('date', '>=', $this->startDate);
        }

        if($this->startDate){
            $query->whereDate('date', '<=', $this->startDate);
        }

        return $query->sum('amount');
    }

    #[Computed]
    public function categories(){
        return Category::where('user_id', Auth::user()->id)
        ->orderBy('name')
        ->get();
    }

    public function updatingSearch(){
        $this->resetPage();
    }

    public function updatingSelectedCategory(){
        $this->resetPage();
    }

     public function updatingStartDate(){
        $this->resetPage();
    }

     public function updatingEndDate(){
        $this->resetPage();
     }

     public function clearFilters(){
        $this->search = '';
        $this->selectedCategory = '';
        $this->startDate = now()->startOfMonth()->format("Y-m-d");
        $this->endDate = now()->endOfMonth()->format("Y-m-d");
        $this->resetPage();
     }
}
?>

<div class="min-h-screen bg-gray-50 dark:bg-neutral-800">
    <!-- Header -->
    <div class="bg-gradient-to-r shadow-sm border-b border-amber-600 from-orange-500 to-red-700">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-white">Expenses</h1>
                    <p class="text-indigo-100 mt-1">Manage and track your expenses</p>
                </div>
                <a href="/expenses/create" class="bg-amber-600 hover:bg-amber-700 text-white px-6 py-3 rounded-lg font-semibold transition flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add Expense
                </a>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 dark:bg-neutral-800">

        @if (session()->has('message'))
            <div class="mb-6 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg flex items-center justify-between">
                <span>{{ session('message') }}</span>
                <button onclick="this.parentElement.remove()" class="text-green-600 hover:text-green-800">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        @endif

        <!-- Filters Section -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-6 dark:bg-neutral-700">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Filters</h3>
                <button wire:click="$toggle('showFilters')" class="text-purple-600 hover:text-purple-700 dark:text-gray-400 dark:hover:text-gray-200 text-sm font-medium">
                    {{ $showFilters ? 'Hide' : 'Show' }} Filters
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Search -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-200">Search</label>
                    <input type="text" 
                           wire:model.live.debounce.300ms="search" 
                           placeholder="Search expenses..."
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent dark:bg-neutral-700 dark:text-gray-200">
                </div>

                <!-- Category Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-200">Category</label>
                    <select wire:model.live="selectedCategory" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent dark:bg-neutral-700 dark:text-gray-200">
                        <option value="">All Categories</option>
                        @foreach($this->categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Start Date -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-200">Start Date</label>
                    <input type="date" 
                           wire:model.live="startDate"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </div>

                <!-- End Date -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-200">End Date</label>
                    <input type="date" 
                           wire:model.live="endDate"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </div>
            </div>

            @if($showFilters)
                <div class="mt-4 flex items-center justify-between pt-4 border-t border-gray-200 ">
                    <div class="text-sm text-gray-600">
                        Showing {{ $this->expenses->count() }} of {{ $this->expenses->total() }} expenses
                        <span class="font-semibold text-gray-900">• Total: ${{ number_format($this->total, 2) }}</span>
                    </div>
                    <button wire:click="clearFilters" class="text-sm text-purple-600 hover:text-purple-700 font-medium dark:text-gray-200 dark:hover:text-gray-400">
                        Clear Filters
                    </button>
                </div>
            @endif
        </div>

        <!-- Expenses Table -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden dark:bg-neutral-700">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-100 dark:bg-neutral-500 border-b border-gray-200 dark:border-neutral-400">
                        <tr>
                            <th wire:click="sortBy('date')" class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-neutral-600">
                                <div class="flex items-center gap-2">
                                    Date
                                    @if($sortBy === 'date')
                                        <svg class="w-4 h-4 {{ $sortDirection === 'asc' ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    @endif
                                </div>
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300 ">
                                Category
                            </th>
                            <th wire:click="sortBy('title')" class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-neutral-600">
                                <div class="flex items-center gap-2">
                                    Title
                                    @if($sortBy === 'title')
                                        <svg class="w-4 h-4 {{ $sortDirection === 'asc' ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    @endif
                                </div>
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300">
                                Description
                            </th>
                            <th wire:click="sortBy('amount')" class="px-6 py-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-neutral-600">
                                <div class="flex items-center justify-end gap-2">
                                    Amount
                                    @if($sortBy === 'amount')
                                        <svg class="w-4 h-4 {{ $sortDirection === 'asc' ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    @endif
                                </div>
                            </th>
                            <th class="px-6 py-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($this->expenses as $expense)
                            <tr class="hover:bg-gray-50 transition" wire:key="expense-{{ $expense->id }}">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ $expense->date->format('M d, Y') }}
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        {{ $expense->date->format('l') }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($expense->category)
                                        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-sm font-medium" 
                                              style="background-color: {{ $expense->category->color }}20; color: {{ $expense->category->color }};">
                                            <span class="w-2 h-2 rounded-full" style="background-color: {{ $expense->category->color }};"></span>
                                            {{ $expense->category->name }}
                                        </span>
                                    @else
                                        <span class="text-gray-400 text-sm dark:text-gray-400">Uncategorized</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900">{{ $expense->title }}</div>
                                    @if($expense->is_auto_generated)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-800 mt-1">
                                            Auto-generated
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-600 max-w-xs truncate">
                                        {{ $expense->description ?: '—' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <div class="text-sm font-bold text-gray-900">
                                        ${{ number_format($expense->amount, 2) }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="/expenses/{{ $expense->id }}/edit" 
                                           class="text-purple-600 hover:text-purple-900 transition">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </a>
                                        <button wire:click="deleteExpense({{ $expense->id }})" 
                                                wire:confirm="Are you sure you want to delete this expense?"
                                                class="text-red-600 hover:text-red-900 transition">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center">
                                        <svg class="w-16 h-16 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        <h3 class="text-lg font-medium text-gray-900 mb-2 dark:text-gray-400">No expenses found</h3>
                                        <p class="text-gray-600 mb-4 dark:text-gray-300">Start tracking your expenses to see them here.</p>
                                        <a href="/expenses/create" class="bg-amber-600 hover:bg-amber-700 text-white px-6 py-2 rounded-lg font-semibold transition ">
                                            Add Your First Expense
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($this->expenses->hasPages())
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $this->expenses->links() }}
                </div>
            @endif
        </div>

    </div>
</div>