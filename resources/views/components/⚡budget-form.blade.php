<?php

use Carbon\Carbon;
use App\Models\Budget;
use Livewire\Component;
use App\Models\Category;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use App\Services\BudgetAIService;

new class extends Component
{
    public $budgetId;
    public $amount = '';
    public $month;
    public $year;
    public $category_id = '';
    
    public $isEdit = false;

      // AI recommendation properties
    public $aiRecommendation = null;
    public $showAIRecommendation = false;
    public $loadingRecommendation = false;
    public $hasHistoricalData = false;

    protected function rules()
    {
        $rules = [
            'amount' => 'required|numeric|min:0.01',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2100',
            'category_id' => 'nullable|exists:categories,id',
        ];

        // Check for duplicate budget
        $uniqueRule = 'unique:budgets,category_id,NULL,id,user_id,' . Auth::user()->id . ',month,' . $this->month . ',year,' . $this->year;
        
        if ($this->isEdit) {
            $uniqueRule = 'unique:budgets,category_id,' . $this->budgetId . ',id,user_id,' . Auth::user()->id . ',month,' . $this->month . ',year,' . $this->year;
        }

        $rules['category_id'] = $this->category_id ? 'required|exists:categories,id|' . $uniqueRule : 'nullable|' . $uniqueRule;

        return $rules;
    }
    protected $messages = [
        'amount.required' => 'Please enter a budget amount.',
        'amount.min' => 'Budget amount must be greater than 0.',
        'month.required' => 'Please select a month.',
        'year.required' => 'Please select a year.',
        'category_id.unique' => 'You already have a budget for this category in this month.',
    ];

    public function mount($budgetId = null)
    {
        if ($budgetId) {
            $this->isEdit = true;
            $this->budgetId = $budgetId;
            $this->loadBudget();
        } else {
            $this->month = now()->month;
            $this->year = now()->year;
            $this->checkHistoricalData();
        }
    }

    public function updatedCategoryId(){
        $aiService = new BudgetAIService();
        $this->hasHistoricalData = $aiService->hasEnoughHistoricalData(
            $this->category_id ?: null,
            auth()->id(),
            $this->month,
            $this->year 
        );

        // reset the Ai recommendations
        $this->aiRecommendation = null;
        $this->showAIRecommendation = false;
    }

    /**
     * Check historical data when month/year changes
     */
    public function updatedMonth()
    {
        $this->checkHistoricalData();
    }

    public function updatedYear()
    {
        $this->checkHistoricalData();
    }


    public function loadBudget()
    {
        $budget = Budget::findOrFail($this->budgetId);
        
        if ($budget->user_id !== Auth::user()->id) {
            abort(403);
        }

        $this->amount = $budget->amount;
        $this->month = $budget->month;
        $this->year = $budget->year;
        $this->category_id = $budget->category_id;
    }

    public function save()
    {
        $this->validate();

        $data = [
            'user_id' => Auth::user()->id,
            'amount' => $this->amount,
            'month' => $this->month,
            'year' => $this->year,
            'category_id' => $this->category_id ?: null,
        ];

        if ($this->isEdit) {
            $budget = Budget::findOrFail($this->budgetId);
            
            if ($budget->user_id !== Auth::user()->id) {
                abort(403);
            }

            $budget->update($data);
            session()->flash('message', 'Budget updated successfully.');
        } else {
            Budget::create($data);
            session()->flash('message', 'Budget created successfully.');
        }

        return redirect()->route('budgets.index');
    }
    #[Computed]
    public function months(){
        return collect(range(1,12))->map(function ($month) {
            return [
                'value' => $month,
                'name' => Carbon::create(null,$month, 1)->format('F'),
            ];
        });
    }
    #[Computed]
    public function years(){
        $currentYear = now()->year;
        return collect(range($currentYear - 1, $currentYear + 2));
    }
       #[Computed]
       public function categories(){
         return Category::where('user_id', Auth::user()->id)
                      ->orderBy('name')
                      ->get();
       }

       private function checkHistoricalData(){
            if ($this->month && $this->year) {
                $aiService = new BudgetAIService();
                $this->hasHistoricalData = $aiService->hasEnoughHistoricalData(
                    $this->category_id ?: null,
                    Auth::user()->id,
                    $this->month,
                    $this->year
                );
            }
       }

       public function getAIRecommendation(){
            $this->loadingRecommendation = true;

            try {
                $aiService = new BudgetAIService();

                $recommendation = $aiService->getBudgetRecommendation(
                    $this->category_id,
                    Auth::user()->id,
                    $this->month,
                    $this->year
                );

                if ($recommendation) {
                    $this->aiRecommendation = $recommendation;
                    $this->showAIRecommendation = true;
                }else{
                    session()->flash('ai-error','Unable to generate recommendation. Please try again.');
                }
            } catch (\Exception $e) {
                    session()->flash('ai-error','Ai service temporarily unavailable. Please try again later.');
                
            }
            $this->loadingRecommendation = false;
       }

       public function applyRecommendation($type = 'recommended'){
            if ($this->aiRecommendation) {
                $this->amount = $this->aiRecommendation[$type] ?? $this->aiRecommendation['recommended'];
            }
       }
    public function closeAIRecommendation()
    {
        $this->showAIRecommendation = false;
    }

       public function render()
    {        
        return $this->view()->title($this->isEdit ? 'Edit Budget' : 'Create Budget');
    }
};
?>

<div>
    <div class="min-h-screen bg-gray-50 dark:bg-neutral-800 rounded">
    <!-- Header -->
    <div class="bg-white dark:bg-neutral-700 shadow-sm border-b border-gray-200">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-200">
                        {{ $isEdit ? 'Edit Budget' : 'Create New Budget' }}
                    </h1>
                    <p class="text-gray-600 dark:text-gray-300 mt-1">{{ $isEdit ? 'Update your budget details' : 'Set spending limits for better financial control' }}</p>
                </div>
                <a href="/budgets" class="text-gray-400 hover:text-gray-900 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </a>
            </div>
        </div>
    </div>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <form wire:submit="save" class="space-y-6">
            
            <!-- Budget Period Card -->
            <div class="bg-white  dark:bg-neutral-700 rounded-xl shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-6 flex items-center gap-2">
                    <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    Budget Period
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Month -->
                    <div>
                        <label for="month" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Month <span class="text-red-500">*</span>
                        </label>
                        <select wire:model="month"
                                id="month"
                                class="w-full px-4 py-3 border dark:bg-neutral-700 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent @error('month') border-red-500 @enderror">
                            <option value="">Select Month</option>
                            @foreach($this->months as $monthOption)
                                <option value="{{ $monthOption['value'] }}">{{ $monthOption['name'] }}</option>
                            @endforeach
                        </select>
                        @error('month')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Year -->
                    <div>
                        <label for="year" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Year <span class="text-red-500">*</span>
                        </label>
                        <select wire:model="year"
                                id="year"
                                class="w-full px-4 py-3 border dark:bg-neutral-700 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent @error('year') border-red-500 @enderror">
                            <option value="">Select Year</option>
                            @foreach($this->years as $yearOption)
                                <option value="{{ $yearOption }}">{{ $yearOption }}</option>
                            @endforeach
                        </select>
                        @error('year')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Budget Details Card -->
            <div class="bg-white dark:bg-neutral-700 rounded-xl shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-6 flex items-center gap-2">
                    <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Budget Details
                </h3>
                
                <div class="space-y-6">
                    <!-- Category -->
                    <div>
                        <label for="category_id" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                            Category
                        </label>
                        <select wire:model="category_id"
                                id="category_id"
                                class="w-full px-4 py-3 border dark:bg-neutral-700 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent @error('category_id') border-red-500 @enderror">
                            <option value="">Overall Budget (All Categories)</option>
                            @foreach($this->categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                        @error('category_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                            Leave blank to create an overall budget, or select a category for specific tracking.
                        </p>
                    </div>

                    <!-- Amount -->
                    <div class="flex items-center justify-between mb-2">
                            <label for="amount" class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                                Budget Amount <span class="text-red-500">*</span>
                            </label>
                            
                            <!-- AI Recommendation Button -->
                            @if($hasHistoricalData && $month && $year && !$isEdit)
                                <button type="button"
                                        wire:click="getAIRecommendation"
                                        wire:loading.attr="disabled"
                                        wire:target="getAIRecommendation"
                                        class="inline-flex items-center gap-2 px-3 py-1.5 bg-gradient-to-r from-purple-600 to-indigo-600 text-white text-sm font-medium rounded-lg hover:shadow-lg transition disabled:opacity-50">
                                    <svg wire:loading.remove wire:target="getAIRecommendation" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                                    </svg>
                                    <svg wire:loading wire:target="getAIRecommendation" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span wire:loading.remove wire:target="getAIRecommendation">✨ Get AI Suggestion</span>
                                    <span wire:loading wire:target="getAIRecommendation">Analyzing...</span>
                                </button>
                            @endif
                        </div>

                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="text-gray-400 text-xl">$</span>
                            </div>
                            <input type="number" 
                                   id="amount"
                                   wire:model="amount" 
                                   step="0.01"
                                   min="0"
                                   placeholder="0.00"
                                   class="w-full pl-10 pr-4 py-3 border border-gray-300 dark:bg-neutral-700 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-lg @error('amount') border-red-500 @enderror">
                        </div>
                        @error('amount')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    
                         <!-- AI Recommendation Panel -->
                    @if($showAIRecommendation && $aiRecommendation)
                        <div class="relative bg-gradient-to-br from-purple-50 via-indigo-50 to-blue-50 border-2 border-purple-200 rounded-xl p-6 shadow-lg">
                            <!-- Close Button -->
                            <button type="button"
                                    wire:click="closeAIRecommendation"
                                    class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>

                            <!-- AI Badge -->
                            <div class="flex items-center gap-2 mb-4">
                                <div class="flex items-center gap-2 px-3 py-1.5 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-full text-sm font-semibold">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                                    </svg>
                                    AI Recommendation
                                </div>
                                <span class="text-xs px-2 py-1 bg-white rounded-full {{ $aiRecommendation['confidence'] === 'high' ? 'text-green-700' : ($aiRecommendation['confidence'] === 'medium' ? 'text-yellow-700' : 'text-orange-700') }}">
                                    {{ ucfirst($aiRecommendation['confidence']) }} confidence
                                </span>
                            </div>

                            <!-- Recommended Amounts -->
                            <div class="grid grid-cols-3 gap-4 mb-4">
                                <button type="button"
                                        wire:click="applyRecommendation('min')"
                                        class="p-4 bg-white rounded-lg border-2 border-gray-200 hover:border-purple-400 hover:shadow-md transition text-center">
                                    <p class="text-xs text-gray-600 mb-1">Conservative</p>
                                    <p class="text-2xl font-bold text-gray-900">${{ number_format($aiRecommendation['min'], 0) }}</p>
                                </button>

                                <button type="button"
                                        wire:click="applyRecommendation('recommended')"
                                        class="p-4 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-lg text-white hover:shadow-xl transition text-center transform hover:scale-105">
                                    <p class="text-xs mb-1 opacity-90">Recommended</p>
                                    <p class="text-2xl font-bold">${{ number_format($aiRecommendation['recommended'], 0) }}</p>
                                </button>

                                <button type="button"
                                        wire:click="applyRecommendation('max')"
                                        class="p-4 bg-white rounded-lg border-2 border-gray-200 hover:border-purple-400 hover:shadow-md transition text-center">
                                    <p class="text-xs text-gray-600 mb-1">Comfortable</p>
                                    <p class="text-2xl font-bold text-gray-900">${{ number_format($aiRecommendation['max'], 0) }}</p>
                                </button>
                            </div>

                            <!-- Explanation -->
                            <div class="bg-white rounded-lg p-4 mb-3">
                                <p class="text-sm text-gray-800 leading-relaxed">
                                    <strong class="text-purple-700">Why this amount:</strong> {{ $aiRecommendation['explanation'] }}
                                </p>
                            </div>

                            <!-- Tip -->
                            <div class="bg-white rounded-lg p-4 border-l-4 border-green-500">
                                <div class="flex items-start gap-3">
                                    <svg class="w-5 h-5 text-green-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                    </svg>
                                    <div>
                                        <p class="text-xs font-semibold text-green-900 mb-1">💡 Pro Tip</p>
                                        <p class="text-sm text-gray-700">{{ $aiRecommendation['tip'] }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Preview Card -->
                    @if($this->amount && $this->month && $this->year)
                        <div class="p-4 bg-gradient-to-r from-indigo-50 to-purple-50 dark:from-gray-300 dark:to-gray-400 rounded-lg border-2 border-indigo-200 dark:border-indigo-500">
                            <p class="text-sm font-medium text-indigo-900 mb-2">Budget Preview:</p>
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-2xl font-bold text-indigo-900">${{ number_format($this->amount, 2) }}</p>
                                    <p class="text-sm text-indigo-700">
                                        {{ $this->category_id ? $this->categories->find($this->category_id)->name : 'Overall Budget' }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm text-indigo-700">
                                        {{ \Carbon\Carbon::create($this->year, $this->month, 1)->format('F Y') }}
                                    </p>
                                    <p class="text-xs text-indigo-600 mt-1">
                                        ≈ ${{ number_format($this->amount / 30, 2) }}/day
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex items-center justify-between">
                <a href="/budgets" class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 dark:text-gray-300 font-semibold hover:bg-gray-50 dark:hover:bg-gray-40 transition">
                    Cancel
                </a>
                <button type="submit" 
                        class="px-8 py-3 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-lg font-semibold hover:shadow-lg transition transform hover:-translate-y-0.5 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    {{ $isEdit ? 'Update Budget' : 'Create Budget' }}
                </button>
            </div>
            <br>
            <!-- Tips Card -->
            <div class="bg-blue-50 dark:bg-blue-200 border border-blue-200 rounded-xl p-6">
                <div class="flex items-start gap-3">
                    <svg class="w-6 h-6 text-blue-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                    <div>
                        <h4 class="font-semibold text-blue-900 mb-2">💡 Budget Tips</h4>
                        <ul class="text-sm text-blue-800 space-y-1">
                            <li>• <strong>Start with historical data:</strong> Review your past spending to set realistic budgets</li>
                            <li>• <strong>Use the 50/30/20 rule:</strong> 50% needs, 30% wants, 20% savings</li>
                            <li>• <strong>Build in buffer:</strong> Add 10% extra for unexpected expenses</li>
                            <li>• <strong>Track regularly:</strong> Check your progress weekly to stay on target</li>
                            @if(!$category_id)
                                <li>• <strong>Overall budgets:</strong> Track total spending across all categories</li>
                            @else
                                <li>• <strong>Category budgets:</strong> Get detailed control over specific spending areas</li>
                            @endif
                        </ul>
                    </div>
                </div>
            </div>

        </form>

        <!-- Examples Section -->
        @if(!$isEdit && $this->categories->count() > 0)
            <div class="mt-8 bg-white dark:bg-neutral-600 rounded-xl shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Budget Examples</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="p-4 bg-gray-50 dark:bg-gray-300 rounded-lg">
                        <p class="font-semibold text-gray-900 mb-2">🍔 Food & Dining</p>
                        <p class="text-sm text-gray-600">Recommended: $400-600/month</p>
                        <p class="text-xs text-gray-500 mt-1">Includes groceries, restaurants, and coffee</p>
                    </div>
                    <div class="p-4 bg-gray-50 dark:bg-gray-300 rounded-lg">
                        <p class="font-semibold text-gray-900 mb-2">🚗 Transportation</p>
                        <p class="text-sm text-gray-600">Recommended: $200-400/month</p>
                        <p class="text-xs text-gray-500 mt-1">Gas, insurance, maintenance, public transit</p>
                    </div>
                    <div class="p-4 bg-gray-50 dark:bg-gray-300 rounded-lg">
                        <p class="font-semibold text-gray-900 mb-2">🎬 Entertainment</p>
                        <p class="text-sm text-gray-600">Recommended: $100-200/month</p>
                        <p class="text-xs text-gray-500 mt-1">Movies, concerts, hobbies, subscriptions</p>
                    </div>
                    <div class="p-4 bg-gray-50 dark:bg-gray-300 rounded-lg">
                        <p class="font-semibold text-gray-900 mb-2">🛒 Shopping</p>
                        <p class="text-sm text-gray-600">Recommended: $150-300/month</p>
                        <p class="text-xs text-gray-500 mt-1">Clothes, electronics, household items</p>
                    </div>
                </div>
                <p class="text-xs text-gray-400 mt-4 text-center">
                    * These are general guidelines. Adjust based on your income and lifestyle.
                </p>
            </div>
        @endif

    </div>
</div>
</div>