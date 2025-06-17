<?php
require_once '../vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

function generateTicketPDF($ticketData) {
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isPhpEnabled', true);

    $dompdf = new Dompdf($options);

    $html = '
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .ticket { 
                border: 2px solid #000;
                padding: 20px;
                margin: 20px;
                max-width: 600px;
            }
            .header {
                text-align: center;
                border-bottom: 1px solid #000;
                padding-bottom: 10px;
                margin-bottom: 20px;
            }
            .details {
                margin-bottom: 20px;
            }
            .details p {
                margin: 5px 0;
            }
            .footer {
                text-align: center;
                margin-top: 20px;
                font-size: 12px;
            }
        </style>
    </head>
    <body>
        <div class="ticket">
            <div class="header">
                <h1>Cinema Ticket</h1>
                <h2>' . htmlspecialchars($ticketData['movie_title']) . '</h2>
            </div>
            <div class="details">
                <p><strong>Ticket ID:</strong> ' . $ticketData['ticket_id'] . '</p>
                <p><strong>Date:</strong> ' . $ticketData['show_date'] . '</p>
                <p><strong>Time:</strong> ' . $ticketData['showtime'] . '</p>
                <p><strong>Studio:</strong> ' . $ticketData['studio'] . '</p>
                <p><strong>Seats:</strong> ' . implode(', ', $ticketData['seats']) . '</p>
                <p><strong>Total Price:</strong> Rp ' . number_format($ticketData['total_price'], 0, ',', '.') . '</p>
            </div>
            <div class="footer">
                <p>Thank you for choosing our cinema!</p>
                <p>Please show this ticket at the entrance.</p>
            </div>
        </div>
    </body>
    </html>';

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A5', 'portrait');
    $dompdf->render();

    // Generate unique filename
    $filename = 'ticket_' . $ticketData['ticket_id'] . '_' . time() . '.pdf';
    $filepath = '../downloads/' . $filename;
    
    // Create downloads directory if it doesn't exist
    if (!file_exists('../downloads')) {
        mkdir('../downloads', 0777, true);
    }

    // Save the PDF
    file_put_contents($filepath, $dompdf->output());

    return $filename;
}
?> 