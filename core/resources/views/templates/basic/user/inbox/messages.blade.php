@php
    $user = auth()->user();
    $whatsapp = @$user->currentWhatsapp();
    $previousDate = null;
    
    // Função para formatar o separador de data
    function getDateSeparator($date) {
        $messageDate = \Carbon\Carbon::parse($date);
        $today = \Carbon\Carbon::today();
        $yesterday = \Carbon\Carbon::yesterday();
        
        // Configurar locale para português
        $messageDate->locale('pt_BR');
        
        if ($messageDate->isSameDay($today)) {
            return 'Hoje';
        } elseif ($messageDate->isSameDay($yesterday)) {
            return 'Ontem';
        } elseif ($messageDate->isCurrentWeek()) {
            // Retorna o nome do dia da semana
            return ucfirst($messageDate->isoFormat('dddd'));
        } else {
            // Para datas mais antigas, mostra a data completa
            return $messageDate->format('d/m/Y');
        }
    }
@endphp

@foreach ($messages->getCollection()->sortBy('ordering') as $message)
    @php
        $currentDate = \Carbon\Carbon::parse($message->created_at)->format('Y-m-d');
        $showDateSeparator = false;
        
        if ($previousDate !== $currentDate) {
            $showDateSeparator = true;
            $previousDate = $currentDate;
        }
    @endphp
    
    @if ($showDateSeparator)
        <div class="date-separator">
            <span class="date-separator__text">{{ getDateSeparator($message->created_at) }}</span>
        </div>
    @endif
    
    @include('Template::user.inbox.single_message', ['message' => $message])
@endforeach
