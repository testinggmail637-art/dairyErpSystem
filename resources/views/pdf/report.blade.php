@extends('pdf.layout')

@section('title', $table->title)

@section('content')
    <p class="muted" style="margin: 0 0 4px 0;">{{ $table->period }}</p>

    <table class="data">
        <thead>
            <tr>
                @foreach ($table->columns as $i => $column)
                    <th class="{{ $table->isNumeric($i) ? 'text-end' : '' }}">{{ $column['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($table->rows as $row)
                <tr>
                    @foreach ($row as $i => $value)
                        <td class="{{ $table->isNumeric($i) ? 'text-end' : '' }}">{{ $table->display($i, $value) }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($table->columns) }}" class="muted">{{ $table->emptyText ?? __('No data in this period.') }}</td></tr>
            @endforelse
        </tbody>
        @if ($table->totals && count($table->rows) > 0)
            <tfoot>
                <tr>
                    @foreach ($table->totals as $i => $value)
                        <td class="{{ $table->isNumeric($i) ? 'text-end' : '' }}">{{ $table->display($i, $value) }}</td>
                    @endforeach
                </tr>
            </tfoot>
        @endif
    </table>

    @foreach ($table->notes as $note)
        <p class="muted" style="margin-top: 8px;">{{ $note }}</p>
    @endforeach
@endsection
