{{-- One value of a scoring matrix. A zero is a quiet dot to the eye and a "0" to a screen reader. --}}
@if ($points > 0)
    <td class="num" style="--heat: {{ round($points / $max, 3) }}">{{ $points }}</td>
@else
    <td class="num zero"><span aria-hidden="true">·</span><span class="visually-hidden">0</span></td>
@endif
