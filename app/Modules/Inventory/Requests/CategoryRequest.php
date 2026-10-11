<?php

namespace App\Modules\Inventory\Requests;

use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('category');

        return $category instanceof Category
            ? $this->user()->can('update', $category)
            : $this->user()->can('create', Category::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $category = $this->route('category');

        return self::rulesFor($category instanceof Category ? $category->category_id : null);
    }

    /**
     * @return array<string, array<mixed>>
     */
    public static function rulesFor(?int $ignoreCategoryId = null): array
    {
        return [
            'category_name' => ['required', 'string', 'max:100', Rule::unique('categories', 'category_name')->ignore($ignoreCategoryId, 'category_id')],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributeNames(): array
    {
        return ['category_name' => 'category name'];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return self::attributeNames();
    }
}
