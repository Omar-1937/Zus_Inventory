{{-- Accessible table view of a chart's data (labels × datasets). --}}
<details class="mt-3 text-sm">
    <summary class="cursor-pointer text-xs font-medium text-accent">Show data table</summary>
    <div class="table-wrap mt-2">
        <table class="table">
            <thead>
                <tr>
                    <th>{{ $labelHeader ?? 'Label' }}</th>
                    @foreach ($datasets as $dataset)
                        <th class="text-right">{{ $dataset['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($labels as $index => $label)
                    <tr>
                        <td>{{ $label }}</td>
                        @foreach ($datasets as $dataset)
                            <td class="text-right tabular-nums">{{ $dataset['data'][$index] ?? 0 }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</details>
