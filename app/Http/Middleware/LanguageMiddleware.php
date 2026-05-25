<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class LanguageMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Get the selected language from session
        $locale = session()->get('locale', 'en');

        // Only translate if a translation is selected and response is HTML
        if ($locale !== 'en' && method_exists($response, 'getContent') && str_contains($response->headers->get('Content-Type'), 'text/html')) {
            $content = $response->getContent();

            // Dictionary for selected language
            $dictionary = $this->getDictionary($locale);

            if (!empty($dictionary)) {
                // To prevent partial replacements (e.g. replacing words within HTML attributes),
                // we sort the dictionary by keys in descending length order so longer phrases are translated first!
                uksort($dictionary, function($a, $b) {
                    return strlen($b) - strlen($a);
                });

                // Run replacing
                foreach ($dictionary as $english => $translated) {
                    $content = str_replace($english, $translated, $content);
                }

                $response->setContent($content);
            }
        }

        return $response;
    }

    private function getDictionary($locale)
    {
        $dicts = [
            'hi' => [
                'Company Dashboard' => 'कंपनी डैशबोर्ड',
                'Welcome back to Build Arya' => 'बिल्ड आर्या सिस्टम में आपका स्वागत है',
                'Date Filter' => 'तिथि फ़िल्टर',
                'Site Filter' => 'साइट फ़िल्टर',
                'Compare Site' => 'साइट की तुलना करें',
                'Filter' => 'फ़िल्टर करें',
                'Switch' => 'बदलें',
                'Total Working Sites' => 'कुल सक्रिय साइटें',
                'Selected Period Expenses' => 'चयनित अवधि के खर्च',
                'Total Employees' => 'कुल कर्मचारी',
                'Flags' => 'चिह्न',
                'Dashboard' => 'डैशबोर्ड',
                'Sites & Users' => 'साइटें और उपयोगकर्ता',
                'Expenses' => 'खर्च',
                'Cost Category' => 'लागत श्रेणी',
                'Material Purchase' => 'सामग्री खरीद',
                'Manage Stock' => 'स्टॉक प्रबंधित करें',
                'Site Bills' => 'साइट बिल',
                'Machinery' => 'मशीनरी',
                'Sales' => 'बिक्री',
                'Payment Vouchers' => 'भुगतान वाउचर',
                'System Management' => 'प्रणाली प्रबंधन',
                'Contacts' => 'संपर्क',
                'Documents' => 'दस्तावेज़',
                'Sign-out' => 'साइन-आउट',
                'Are You Sure!' => 'क्या आप सुनिश्चित हैं!',
                'Do You Really Want To Logout!' => 'क्या आप सचमुच लॉगआउट करना चाहते हैं!',
                'Logout' => 'लॉगआउट',
                'Cancel' => 'रद्द करें',
                'Please wait...' => 'कृपया प्रतीक्षा करें...',
            ],
            'es' => [
                'Company Dashboard' => 'Tablero de la Empresa',
                'Welcome back to Build Arya' => 'Bienvenido de nuevo a Build Arya',
                'Date Filter' => 'Filtro de Fecha',
                'Site Filter' => 'Filtro de Sitio',
                'Compare Site' => 'Comparar Sitio',
                'Filter' => 'Filtrar',
                'Switch' => 'Cambiar',
                'Total Working Sites' => 'Total de Sitios de Trabajo',
                'Selected Period Expenses' => 'Gastos del Período Seleccionado',
                'Total Employees' => 'Total de Empleados',
                'Flags' => 'Banderas',
                'Dashboard' => 'Tablero',
                'Sites & Users' => 'Sitios y Usuarios',
                'Expenses' => 'Gastos',
                'Cost Category' => 'Categoría de Costo',
                'Material Purchase' => 'Compra de Material',
                'Manage Stock' => 'Gestionar Stock',
                'Site Bills' => 'Facturas del Sitio',
                'Machinery' => 'Maquinaria',
                'Sales' => 'Ventas',
                'Payment Vouchers' => 'Comprobantes de Pago',
                'System Management' => 'Gestión del Sistema',
                'Contacts' => 'Contactos',
                'Documents' => 'Documentos',
                'Sign-out' => 'Cerrar sesión',
                'Are You Sure!' => '¿Está seguro!',
                'Do You Really Want To Logout!' => '¿Realmente desea cerrar sesión!',
                'Logout' => 'Cerrar sesión',
                'Cancel' => 'Cancelar',
                'Please wait...' => 'Por favor espere...',
            ],
            'te' => [
                'Company Dashboard' => 'కంపెనీ డాష్‌బోర్డ్',
                'Welcome back to Build Arya' => 'బిల్డ్ ఆర్య సిస్టమ్‌కు స్వాగతం',
                'Date Filter' => 'తేదీ ఫిల్టర్',
                'Site Filter' => 'సైట్ ఫిల్టర్',
                'Compare Site' => 'సైట్ పోల్చండి',
                'Filter' => 'ఫిల్టర్',
                'Switch' => 'మార్చండి',
                'Total Working Sites' => 'మొత్తం పని సైట్లు',
                'Selected Period Expenses' => 'ఎంచుకున్న కాల ఖర్చులు',
                'Total Employees' => 'మొత్తం ఉద్యోగులు',
                'Flags' => 'ఫ్లాగ్‌లు',
                'Dashboard' => 'డాష్‌బోర్డ్',
                'Sites & Users' => 'సైట్లు & వినియోగదారులు',
                'Expenses' => 'ఖర్చులు',
                'Cost Category' => 'ఖర్చు వర్గం',
                'Material Purchase' => 'మెటీరియల్ కొనుగోలు',
                'Manage Stock' => 'స్టాక్ నిర్వహించండి',
                'Site Bills' => 'సైట్ బిల్లులు',
                'Machinery' => 'యంత్రాలు',
                'Sales' => 'అమ్మకాలు',
                'Payment Vouchers' => 'చెల్లింపు వోచర్లు',
                'System Management' => 'సిస్టమ్ నిర్వహణ',
                'Contacts' => 'పరిచయాలు',
                'Documents' => 'పత్రాలు',
                'Sign-out' => 'సైన్-అవుట్',
                'Are You Sure!' => 'మీరు ఖచ్చితంగా అనుకుంటున్నారా!',
                'Do You Really Want To Logout!' => 'మీరు నిజంగా లాగ్అవుట్ చేయాలనుకుంటున్నారా!',
                'Logout' => 'లాగ్అవుట్',
                'Cancel' => 'రద్దు చేయి',
                'Please wait...' => 'దయచేసి వేచి ఉండండి...',
            ],
            'ta' => [
                'Company Dashboard' => 'நிறுவன டாஷ்போர்டு',
                'Welcome back to Build Arya' => 'பில்ட் ஆர்யா சிஸ்டத்திற்கு நல்வரவு',
                'Date Filter' => 'தேதி வடிகட்டி',
                'Site Filter' => 'தள வடிகட்டி',
                'Compare Site' => 'தளத்தை ஒப்பிடுக',
                'Filter' => 'வடிகட்டு',
                'Switch' => 'மாற்று',
                'Total Working Sites' => 'மொத்த வேலை தளங்கள்',
                'Selected Period Expenses' => 'தேர்ந்தெடுக்கப்பட்ட கால செலவுகள்',
                'Total Employees' => 'மொத்த ஊழியர்கள்',
                'Flags' => 'கொடிகள்',
                'Dashboard' => 'டாஷ்போர்டு',
                'Sites & Users' => 'தளங்கள் & பயனர்கள்',
                'Expenses' => 'செலவுகள்',
                'Cost Category' => 'செலவு வகை',
                'Material Purchase' => 'பொருள் கொள்முதல்',
                'Manage Stock' => 'பங்குகளை நிர்வகி',
                'Site Bills' => 'தள பில்கள்',
                'Machinery' => 'இயந்திரங்கள்',
                'Sales' => 'விற்பனை',
                'Payment Vouchers' => 'கட்டண வவுச்சர்கள்',
                'System Management' => 'அமைப்பு மேலாண்மை',
                'Contacts' => 'தொடர்புகள்',
                'Documents' => 'ஆவணங்கள்',
                'Sign-out' => 'வெளியேறு',
                'Are You Sure!' => 'நிச்சயமாக இருக்கிறீர்களா!',
                'Do You Really Want To Logout!' => 'நீங்கள் உண்மையில் வெளியேற வேண்டுமா!',
                'Logout' => 'வெளியேறு',
                'Cancel' => 'ரத்துசெய்',
                'Please wait...' => 'தயவுசெய்து காத்திருக்கவும்...',
            ]
        ];

        return $dicts[$locale] ?? [];
    }
}
