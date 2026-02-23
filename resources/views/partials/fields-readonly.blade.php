@php
/**
 * Recursive partial for rendering fields in read-only preview mode.
 * @var mixed $fields
 * @var string $parentPath
 * @var array $fieldMeta
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
            $meta = PreviewService::resolveFieldMeta($dataPath, (string)$key, $fieldMeta ?? []);
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
            <div class="field-row" data-field-key="{{ $key }}"@if($isCondTarget) data-cond-target="{{ $key }}"@endif>
                <div class="field-name">{{ $fieldLabel }}</div>
                <div class="field-value{{ empty($value) ? ' empty' : '' }}">
                    {{ $value ?: '(empty)' }}
                </div>
            </div>
        @elseif(is_array($value))
            @if(isset($value[0]))
                <div class="nested-group" data-field-key="{{ $key }}"@if($isCondTarget) data-cond-target="{{ $key }}"@endif>
                    <div class="group-title">{{ $fieldLabel }} <span class="badge list-count">{{ count($value) }} items</span></div>
                    @foreach($value as $i => $item)
                        @if(is_array($item))
                            <div class="nested-group">
                                <div class="group-title">Item {{ $i + 1 }}</div>
                                @include('xfa-pdf::partials.fields-readonly', ['fields' => $item, 'parentPath' => $dataPath . '[' . $i . ']', 'fieldMeta' => $fieldMeta ?? [], 'conditionalRules' => $conditionalRules])
                            </div>
                        @else
                            <div class="field-row" data-field-key="{{ $key }}">
                                <div class="field-name">{{ $fieldLabel }} [{{ $i + 1 }}]</div>
                                <div class="field-value{{ empty($item) ? ' empty' : '' }}">{{ $item ?: '(empty)' }}</div>
                            </div>
                        @endif
                    @endforeach
                </div>
            @else
                <div class="nested-group" data-field-key="{{ $key }}"@if($isCondTarget) data-cond-target="{{ $key }}"@endif>
                    <div class="group-title">{{ $fieldLabel }}</div>
                    @include('xfa-pdf::partials.fields-readonly', ['fields' => $value, 'parentPath' => $dataPath, 'fieldMeta' => $fieldMeta ?? [], 'conditionalRules' => $conditionalRules])
                </div>
            @endif
        @endif
    @endforeach
@else
    <div class="field-row">
        <span class="field-value empty">No data</span>
    </div>
@endif
