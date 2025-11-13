import requests
import json
import sys

class WebSocketClient:
    def __init__(self, url):
        self.url = url
        
    def send_command(self, command):
        """Envía un comando al servidor WebSocket"""
        try:
            payload = {'command': command}
            headers = {'Content-Type': 'application/json'}
            
            response = requests.post(
                self.url,
                data=json.dumps(payload),
                headers=headers,
                timeout=2
            )
            
            if response.status_code == 200:
                print(f"✓ Comando enviado exitosamente")
                try:
                    result = response.json()
                    print(f"Respuesta: {json.dumps(result, indent=2)}")
                except:
                    print(f"Respuesta: {response.text}")
            else:
                print(f"✗ Error: {response.status_code} - {response.text}")
                
        except requests.exceptions.Timeout:
            print("✓ Comando enviado (timeout esperado)")
        except requests.exceptions.ConnectionError:
            print("✗ Error: No se pudo conectar al servidor")
        except Exception as e:
            print(f"✗ Error inesperado: {str(e)}")

def main():
    # URL del servidor WebSocket
    WEBSOCKET_URL = 'http://192.168.128.15:8081/send-command'
    
    client = WebSocketClient(WEBSOCKET_URL)
    
    print("=" * 60)
    print("Cliente WebSocket - Envío de Comandos")
    print("=" * 60)
    print(f"Conectado a: {WEBSOCKET_URL}")
    print("\nComandos disponibles:")
    print("  - Escribe tu comando y presiona Enter")
    print("  - 'exit' o 'quit' para salir")
    print("  - 'help' para ver ejemplos")
    print("=" * 60)
    print()
    
    while True:
        try:
            # Leer comando del usuario
            command = input(">> ").strip()
            
            if not command:
                continue
                
            # Comandos especiales
            if command.lower() in ['exit', 'quit', 'q']:
                print("\n¡Adiós!")
                break
                
            if command.lower() == 'help':
                print("\nEjemplos de comandos:")
                print('  alertaPerzonalizada("success", "Operación exitosa");')
                print('  alertaPerzonalizada("error", "Ha ocurrido un error");')
                print('  alertaPerzonalizada("warning", "Advertencia importante");')
                print('  alertaPerzonalizada("info", "Información relevante");')
                print()
                continue
            
            # Enviar comando
            client.send_command(command)
            print()
            
        except KeyboardInterrupt:
            print("\n\n¡Adiós!")
            break
        except EOFError:
            break

if __name__ == "__main__":
    main()