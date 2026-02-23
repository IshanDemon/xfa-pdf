@php
/**
 * Recursive partial for rendering fields in edit mode with form controls.
 * @var mixed $fields
 * @var string $parentPath
 * @var array $fieldMeta
 * @var array $repeatables
 * @var array $conditionalRules
 */
use Xfa\Pdf\Services\PreviewService;
$conditionalRules = $conditionalRules ?? [];
@endphp

@if(is_string($fields))
    {{-- handled by parent --}}
@elseif(is_array($fields) && !empty($fields))
    @foreach($fields as $key => $value)
        @php
            $dataPath = $parentPath ? $parentPath . '.' . $key : (string)$key;
            $inputName = 'fields[' . str_replace('.', '][', $dataPath) . ']';
            $isRepeatable = isset($repeatables[$key]);
            $meta = PreviewService::resolveFieldMeta($dataPath, (string)$key, $fieldMeta);
            $fieldLabel = !empty($meta['caption']) ? $meta['caption'] : PreviewService::humanize((string)$key);
            $isCondTarget = false;
            foreach ($conditionalRules as $trigger => $rule) {
                if (in_array((string)$key, $rule['targets'] ?? [])) {
                    $isCondTarget = true;
                    break;
                }
            }
        @endphp

        @if(is_string($value))
            @php
                $meta = PreviewService::resolveFieldMeta($dataPath, (string)$key, $fieldMeta);
                $type = $meta['type'] ?? 'text';
                $options = $meta['options'] ?? [];
                $caption = $meta['caption'] ?? '';
                $isTrigger = isset($conditionalRules[(string)$key]);
            @endphp
            <div class="field-row" data-field-key="{{ $key }}"@if($isCondTarget) data-cond-target="{{ $key }}"@endif>
                <div class="field-name">{{ $fieldLabel }}</div>
                <div class="field-control">
                    @if($type === 'select')
                        <select name="{{ $inputName }}"@if($isTrigger) data-cond-trigger="{{ $key }}"@endif>
                            @if(empty($options) || !in_array($value, $options))
                                <option value="{{ $value }}" selected>{{ $value }}</option>
                            @endif
                            @foreach($options as $opt)
                                <option value="{{ $opt }}" {{ $opt === $value ? 'selected' : '' }}>{{ $opt }}</option>
                            @endforeach
                        </select>
                    @elseif($type === 'radio')
                        <div class="radio-group"@if($isTrigger) data-cond-trigger="{{ $key }}"@endif>
                            @foreach($options as $opt)
                                <label>
                                    <input type="radio" name="{{ $inputName }}" value="{{ $opt }}" {{ $opt === $value ? 'checked' : '' }}>
                                    {{ ucfirst($opt) }}
                                </label>
                            @endforeach
                        </div>
                    @elseif($type === 'checkbox')
                        <div class="checkbox-wrap">
                            <input type="hidden" name="{{ $inputName }}" value="0">
                            <input type="checkbox" name="{{ $inputName }}" value="1" {{ in_array($value, ['1', 'true', 'yes']) ? 'checked' : '' }}>
                            <label>{{ $caption ?: 'Yes' }}</label>
                        </div>
                    @elseif($type === 'date')
                        @php
                            $htmlDate = $value;
                            if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $d)) {
                                $htmlDate = $d[3] . '-' . $d[2] . '-' . $d[1];
                            }
                        @endphp
                        <input type="date" name="{{ $inputName }}" value="{{ $htmlDate }}">
                    @elseif($type === 'number')
                        <input type="number" name="{{ $inputName }}" value="{{ $value }}">
                    @elseif($type === 'textarea')
                        <textarea name="{{ $inputName }}" rows="3">{{ $value }}</textarea>
                    @else
                        <input type="text" name="{{ $inputName }}" value="{{ $value }}">
                    @endif
                </div>
            </div>
        @elseif(is_array($value))
            @if(isset($value[0]))
                {{-- Indexed array (multiple items) --}}
                @php $count = count($value); @endphp
                <div class="nested-group{{ $isRepeatable ? ' repeatable-group' : '' }}" data-field-key="{{ $key }}"@if($isCondTarget) data-cond-target="{{ $key }}"@endif>
                    <div class="group-title">{{ $fieldLabel }}
                        <span class="badge list-count">{{ $count }} items</span>
                        @if($isRepeatable)
                            <button type="button" class="btn-add-item" onclick="addItem(this)">+ Add Item</button>
                        @endif
                    </div>
                    @foreach($value as $i => $item)
                        @if(is_array($item))
                            @php $itemPath = $dataPath . '[' . $i . ']'; @endphp
                            @if($isRepeatable)<div class="repeatable-item">@endif
                            <div class="nested-group">
                                <div class="group-title">
                                    @if($isRepeatable)
                                        <span class="item-label">Item {{ $i + 1 }}</span>
                                        <button type="button" class="btn-remove-item" onclick="removeItem(this)">Remove</button>
                                    @else
                                        Item {{ $i + 1 }}
                                    @endif
                                </div>
                                @include('xfa-pdf::partials.fields-editable', [
                                    'fields' => $item,
                                    'parentPath' => $itemPath,
                                    'fieldMeta' => $fieldMeta,
                                    'repeatables' => $repeatables,
                                    'conditionalRules' => $conditionalRules,
                                ])
                            </div>
                            @if($isRepeatable)</div>@endif
                        @else
                            @php
                                $itemMeta = PreviewService::resolveFieldMeta($dataPath, (string)$key, $fieldMeta);
                                $itemInputName = 'fields[' . str_replace('.', '][', $dataPath) . '][' . $i . ']';
                            @endphp
                            <div class="field-row" data-field-key="{{ $key }}">
                                <div class="field-name">{{ $fieldLabel }} [{{ $i + 1 }}]</div>
                                <div class="field-control">
                                    <input type="text" name="{{ $itemInputName }}" value="{{ $item }}">
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            @else
                {{-- Single associative array (nested group) --}}
                @if($isRepeatable)
                    <div class="nested-group repeatable-group" data-field-key="{{ $key }}"@if($isCondTarget) data-cond-target="{{ $key }}"@endif>
                        <div class="group-title">{{ $fieldLabel }}
                            <span class="badge list-count">1 items</span>
                            <button type="button" class="btn-add-item" onclick="addItem(this)">+ Add Item</button>
                        </div>
                        <div class="repeatable-item">
                            <div class="nested-group">
                                <div class="group-title">
                                    <span class="item-label">Item 1</span>
                                    <button type="button" class="btn-remove-item" onclick="removeItem(this)">Remove</button>
                                </div>
                                @include('xfa-pdf::partials.fields-editable', [
                                    'fields' => $value,
                                    'parentPath' => $dataPath . '[0]',
                                    'fieldMeta' => $fieldMeta,
                                    'repeatables' => $repeatables,
                                    'conditionalRules' => $conditionalRules,
                                ])
                            </div>
                        </div>
                    </div>
                @else
                    <div class="nested-group" data-field-key="{{ $key }}"@if($isCondTarget) data-cond-target="{{ $key }}"@endif>
                        <div class="group-title">{{ $fieldLabel }}</div>
                        @include('xfa-pdf::partials.fields-editable', [
                            'fields' => $value,
                            'parentPath' => $dataPath,
                            'fieldMeta' => $fieldMeta,
                            'repeatables' => $repeatables,
                            'conditionalRules' => $conditionalRules,
                        ])
                    </div>
                @endif
            @endif
        @endif
    @endforeach
@else
    <div class="field-row">
        <span class="field-control" style="color:#aaa;font-style:italic;padding:6px 0;">No data</span>
    </div>
@endif
