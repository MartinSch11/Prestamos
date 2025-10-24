<!-- nueva-reserva.blade.php -->
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Reserva</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #f5f5f5;
            line-height: 1.6;
        }

        .email-container {
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
        }

        .header {
            background: #1F2937;
            padding: 20px 25px;
            text-align: center;
            color: #ffffff;
        }

        .header h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
        }

        .header p {
            margin: 8px 0 0 0;
            font-size: 14px;
            opacity: 0.8;
        }

        .content {
            padding: 15px 20px;
        }

        .content p {
            margin: 5px 0;
            font-size: 16px;
            color: #374151;
        }

        .greeting {
            font-size: 18px;
            color: #111827;
            margin-bottom: 15px;
            font-weight: 500;
        }

        .info-box {
            border-left: 4px solid #2563EB;
            background-color: #f9fafb;
            padding: 20px;
            margin: 25px 0;
            border-radius: 6px;
        }

        .info-row {
            display: flex;
            margin-bottom: 12px;
            align-items: flex-start;
        }

        .info-row:last-child {
            margin-bottom: 0;
        }

        .info-label {
            font-weight: 600;
            color: #4b5563;
            min-width: 110px;
            font-size: 14px;
        }

        .info-value {
            color: #111827;
            font-size: 14px;
            font-weight: 500;
        }

        .button-container {
            text-align: center;
            margin: 40px 0 30px 0;
        }

        .button {
            display: inline-block;
            padding: 14px 32px;
            background: #2563EB;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            transition: background-color 0.2s, transform 0.2s;
        }

        .button:hover {
            background: #1D4ED8;
            transform: translateY(-2px);
        }

        .footer {
            background-color: #1F2937;
            padding: 25px 30px;
            text-align: center;
            color: #9CA3AF;
            font-size: 13px;
            border-top: none;
        }

        .footer p {
            margin: 5px 0;
        }

        .footer strong {
            color: #D1D5DB;
        }

        .equipos-section {
            margin: 25px 0;
        }

        .equipos-title {
            font-weight: 600;
            color: #111827;
            font-size: 15px;
            margin-top: 20px;
            margin-bottom: 12px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
        }

        .equipo-item {
            padding: 8px 0px;
            margin: 0px;
            border-bottom: 1px solid #e5e7eb;
        }

        .equipo-item:last-child {
            border-bottom: none;
        }

        .equipo-nombre {
            color: #111827;
            font-size: 14px;
            font-weight: 500;
        }

        .equipo-cantidad {
            color: #6b7280;
            font-size: 14px;
        }
    </style>
</head>

<body>
    <div class="email-container">
        <div class="header">
            <h1>Nueva Reserva Pendiente</h1>
            <p>Sistema de Gestión de Equipos</p>
        </div>

        <!-- <div class="header">
            <h1>Nueva Reserva Pendiente</h1>
            <p>Universidad Gastón Dachary - Sistema de Gestión de Equipos</p>
        </div> -->

        <div class="content">
            <p class="greeting">Hola {{ $admin->name }},</p>

            <p>El alumno <strong>{{ $reserva->user->name }}</strong> ha solicitado una nueva reserva de equipos
                fotográficos.</p>

            <div class="info-box">
                <div class="info-row">
                    <span class="info-label">Alumno:</span>
                    <span class="info-value">{{ $reserva->user->name }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Fecha de inicio: </span>
                    <span class="info-value">{{ $reserva->inicio->format('d/m/Y H:i') }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Fecha de fin: </span>
                    <span class="info-value">{{ $reserva->fin->format('d/m/Y H:i') }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Duración:</span>
                    <span class="info-value">{{ $reserva->inicio->diffInDays($reserva->fin) }} día(s)</span>
                </div>
                <p class="equipos-title">Equipos solicitados:</p>
                @foreach($reserva->items as $item)
                    <div class="equipo-item">
                        <span class="equipo-nombre">{{ $item->equipo->nombre }}</span>
                        <span class="equipo-cantidad"> x {{ $item->cantidad }}</span>
                    </div>
                @endforeach
            </div>

            <p>Por favor, revisa los detalles de la reserva y gestiona la solicitud lo antes posible.</p>

            <div class="button-container">
                <a href="{{ $calendarUrl }}" class="button">Ver Detalles en el Calendario</a>
            </div>
        </div>

        <div class="footer">
            <p><strong>Sistema de Reservas de Equipos Fotográficos</strong></p>
            <p>Este es un correo automático, por favor no responder.</p>
        </div>
    </div>
</body>

</html>