<?php

	require_once('lib/FirePHPCore/FirePHP.class.php');
	$firephp = FirePHP::getInstance(true);

	//$recieved_json = $_POST;
	$sensor = $_POST['sensor'];
	$act = $_POST['actuator'];
	$act_ip = $_POST['ip'];

// 	$firephp->log($sensor,'sensor');
// 	$firephp->log($act,'actuator');
	$firephp->log($act_ip,'actuator ip');
	
// 	$sensor_names = ['S_Ultrasonic','S_Humidity','S_Temperature','S_Heatindex','S_Light','S_Gas','S_IR'];
// 	$act_names = ['A_Fan','A_Servo','A_Buzzer'];
	
	//actuator source code
	$a_header = "#include <arpa/inet.h>
				#include <netinet/in.h>
				#include <stdbool.h> // bool, true, false
				#include <stdio.h>
				#include <stdlib.h>
				#include <unistd.h>
				#include <time.h>
				#include <math.h>
				#include <string.h> // memset()
				#include <sys/socket.h>
				#include <termios.h>
				#include <pthread.h>
				#include <errno.h>
				#include 'piActuator.h'
				#include 'uart_api.h'";
	$a_main = "int main(){
				    pthread_t p_thread;
				    int tid;
				    int status;
				
				    tid = pthread_create(&p_thread, NULL, thread_recvDeviceInfoFromClient, NULL);
				    if (tid < 0)
				    {
				        perror('thread_recvDeviceInfoFromClient() create error');
				        exit(1);
				    }
				
				    pthread_join(p_thread, (void **)&status);
				
				    return 0;
				}";
	$a_recv_start = "void *thread_recvDeviceInfoFromClient(void *tData){
						    int fd;
						    int client_fd;
						    int server_fd;
						    struct sockaddr_in client_addr;
						    struct sockaddr_in server_addr;
						    int timeout = 0;
						    Sensor sensor;
						
						    if ((server_fd = socket(AF_INET, SOCK_STREAM, IPPROTO_TCP)) < 0)
						    {
						        printf('> Fail work to create socket fd!\n');
						        exit(1);
						    }
						
						    printf('> Create server socket fd: %d\n', server_fd);
						
						    memset((void *)&server_addr, 0x00, sizeof(server_addr));
						    server_addr.sin_family = AF_INET;
						    server_addr.sin_addr.s_addr = htonl(INADDR_ANY);
						    server_addr.sin_port = htons(12122);
						
						    bind(server_fd, (struct sockaddr *)&server_addr, sizeof(server_addr));
						    listen(server_fd, 5);
						
						    socklen_t client_size = sizeof(client_addr);
						    client_fd = accept(server_fd, (struct sockaddr *)&client_addr, &client_size);
						
						    if (client_fd == -1)
						    {
						        perror('> Accept error');
						    }
						
						    openDevice(&fd);";
	//수정되어야 하는 코드
	$a_recv_while = "while (true){
					        char level[2];
					
					        if (read(client_fd, &sensor, sizeof(Sensor)) > 0)
					        {
					            timeout = 0;
					            printf('> Client(%s) is connected\n', inet_ntoa(client_addr.sin_addr));
					            printf('  Ultrasonic\t: %03d\t\t IR\t\t: %d\n', sensor.ultrasonic, sensor.ir);
					            printf('  Humidity\t: %02d\t\t Temperature\t: %02d\n', sensor.humidity, sensor.temperature);
					            printf('  Heatindex\t: %02.2f\t\t Light\t\t: %03d\n', sensor.heatindex, sensor.light);
					            printf('  Gas\t\t: %04d\n', sensor.gas);
					
					            // TODO: Modify actuate condition.
					            // BEGIN
					            if (sensor.light > 800)
					            {
					                strcpy(level, '4');
					            }
					            else if (sensor.light > 600)
					            {
					                strcpy(level, '3');
					            }
					            else if (sensor.light > 400)
					            {
					                strcpy(level, '2');
					            }
					            else if (sensor.light > 200)
					            {
					                strcpy(level, '1');
					            }
					            else
					            {
					                strcpy(level, '0');
					            }
					            // END
					
					            // TCIFLUSH	 수신했지만 읽어들이지 않은 데이터를 버립니다.
					            // TCOFLUSH	 쓰기응이지만 송신하고 있지 않는 데이터를 버립니다.
					            // TCIOFLUSH 수신했지만 읽어들이지 않은 데이터, 및 쓰기응이지만 송신하고 있지 않는 데이터의 양쪽 모두를 버립니다.
					            tcflush(fd, TCIOFLUSH);
					            user_uart_write(fd, (unsigned char *)level, 2);
					            printf('  %d, %s\n', fd, level);
					        }
					        else
					        {
					            if (errno != EINTR)
					            {
					                printf('  Client(%s) Timeout Count: %d\n', inet_ntoa(client_addr.sin_addr), ++timeout);
					
					                if (timeout > 60)
					                {
					                    printf('> Client(%s) is closed..\n', inet_ntoa(client_addr.sin_addr));
					                    close(client_fd);
					                    return 0;
					                }
					
					                delay(1);
					            }
					        }
					    }
					}";
	$a_end = "void openDevice(int *fd)
				{
				    if (access(USB_SERIAL, R_OK & W_OK) == 0)
				    {
				        printf('> %s is accessable\n', USB_SERIAL);
				
				        *fd = user_uart_open(USB_SERIAL);
				        if (*fd != -1)
				        {
				            user_uart_config(*fd, 115200, 8, 0, 1);
				
				            printf('> %s is opened\n', USB_SERIAL);
				            printf('> Configure UART: baud rate %d, data bit %d, stop bit %d, parity bit %d\n', BAUD_RATE, DATA_BIT, STOP_BIT, PARITY_BIT);
				        }
				        else
				        {
				            printf('> %s is not openned.\nPlease, check device!\n', USB_SERIAL);
				            exit(1);
				        }
				    }
				    else
				    {
				        printf('> %s is not accessable.\nPlease, check device!\n', USB_SERIAL);
				        exit(1);
				    }
				}
				
				void delay(float time)
				{
				    struct timespec req = {0};
				    double s;
				    double ms;
				    ms = modf(time, &s) * 1000000000;
				
				    req.tv_sec = s;
				    req.tv_nsec = ms;
				    while (nanosleep(&req, NULL) && errno == EINTR);
				}
				
				void printfln()
				{
				    printf('\n');
				}";
	
	//sensor source code
	$s_header = "#include <arpa/inet.h>
				#include <stdbool.h> // bool, true, false
				#include <netinet/in.h>
				#include <stdio.h>
				#include <stdlib.h>
				#include <string.h> // memset()
				#include <math.h>
				#include <sys/socket.h>
				#include <termios.h>
				#include <pthread.h>
				#include <time.h>
				#include <unistd.h>
				#include <errno.h>
				#include 'piSensor.h'
				#include 'uart_api.h'";
	$s_main = "int main()
				{
				    pthread_t p_thread;
				    int tid;
				    int status;
				
				    tid = pthread_create(&p_thread, NULL, thread_sendDeviceInfoToServer, NULL);
				    if (tid < 0)
				    {
				        perror('thread_sendDeviceInfoToServer() create error');
				        exit(1);
				    }
				
				    pthread_join(p_thread, (void **)&status);
				
				    return 0;
				}";	
	$s_send_front = "void *thread_sendDeviceInfoToServer(void *tData)
					{
					    int fd;
					    unsigned char data[SERIAL_MAX_BUFF] = {0};
					
					    int server_fd;
					    struct sockaddr_in server_addr;
					    if ((server_fd = socket(AF_INET, SOCK_STREAM, 0)) < 0)
					    {
					        printf('> Fail work to create socket fd!\n');
					        exit(1);
					    }
					    printf('> Create server socket fd: %d\n', server_fd);
					
					    memset((void *)&server_addr, 0x00, sizeof(server_addr));
					    server_addr.sin_family = AF_INET;";
	//수정되어야 하는 코드 (ip를 선택된 actuator ip로 수정)
	$s_send_mod = "server_addr.sin_addr.s_addr = inet_addr('"+$act_ip+"');";
	$s_end = "server_addr.sin_port = htons(12122);
			    while (connect(server_fd, (struct sockaddr *)&server_addr, sizeof(server_addr)) == -1)
			    {
			        perror('> Connect to server error');
			        delay(1);
			    }
			
			    Sensor sensor;
			
			    openDevice(&fd);
			    while (true)
			    {
			        readPacket(fd, data);
			        // TEST_setSensorStruct(&sensor);
			        setDataFromPacket(&sensor, data);
			        write(server_fd, &sensor, sizeof(Sensor));
			        delay(1);
			    }
			}
			
			void openDevice(int *fd)
			{
			    if (access(USB_SERIAL, R_OK & W_OK) == 0)
			    {
			        printf('> %s is accessable\n', USB_SERIAL);
			
			        *fd = user_uart_open(USB_SERIAL);
			        if (*fd != -1)
			        {
			            user_uart_config(*fd, 115200, 8, 0, 1);
			
			            printf('> %s is opened\n', USB_SERIAL);
			            printf('> Configure UART: baud rate %d, data bit %d, stop bit %d, parity bit %d\n', BAUD_RATE, DATA_BIT, STOP_BIT, PARITY_BIT);
			        }
			    }
			    else
			    {
			        printf('> %s is not accessable.\nPlease, check device!\n', USB_SERIAL);
			        exit(1);
			    }
			}
			
			void readPacket(int fd, unsigned char *data)
			{
			    int readSize;
			    int readTotalSize;
			    unsigned char buff[SERIAL_MAX_BUFF] = {0};
			    unsigned char temp_buff[SERIAL_MAX_BUFF] = {0};
			    bzero(data, SERIAL_MAX_BUFF);
			
			    // TCIFLUSH	 수신했지만 읽어들이지 않은 데이터를 버립니다.
			    // TCOFLUSH	 쓰기응이지만 송신하고 있지 않는 데이터를 버립니다.
			    // TCIOFLUSH 수신했지만 읽어들이지 않은 데이터, 및 쓰기응이지만 송신하고 있지 않는 데이터의 양쪽 모두를 버립니다.
			    tcflush(fd, TCIFLUSH);
			    printf('> Read packet data of sensor.\n');
			
			    for (readTotalSize = 0; readTotalSize < SERIAL_MAX_BUFF; readTotalSize += readSize)
			    {
			        if ((readSize = user_uart_read(fd, temp_buff, SERIAL_MAX_BUFF)) == -1)
			        {
			            readSize = 0;
			            continue;
			        }
			
			        strncat_s(buff, temp_buff, readTotalSize, readSize);
			    }
			
			    processPacket(data, buff, readTotalSize);
			}
			
			void processPacket(unsigned char *data, unsigned char *buff, int buff_size)
			{
			    int index_start = 0;
			    int index_end = 0;
			    bool start = false;
			
			    // printf('  ');
			    // for (int i = 0; i < buff_size; i++)
			    // {
			    //     printf('%02X ', buff[i]);
			    // }
			    // printfln();
			
			    for (int i = 0; i < buff_size; i++)
			    {
			        if (buff[i] == START_BIT1 &&
			            buff[i + 1] == START_BIT2 &&
			            !start)
			        {
			            start = true;
			            index_start = i;
			        }
			        else if (buff[i] == END_BIT1 &&
			                 buff[i + 1] == END_BIT2 &&
			                 start)
			        {
			            start = false;
			            index_end = i + 1;
			        }
			
			        if ((index_end - index_start) == 5 ||
			            (index_end - index_start) == 19)
			        {
			            break;
			        }
			        else
			        {
			            continue;
			        }
			    }
			
			    for (int i = 0, j = index_start; j <= index_end; i++, j++)
			    {
			        data[i] = buff[j];
			    }
			}
			
			void setDataFromPacket(Sensor *sensor, unsigned char data[SERIAL_MAX_BUFF])
			{
			    printf('> Set sensing data from packet.\n');
			    if (data[S_START_COL1] == START_BIT1 &&
			        data[S_START_COL2] == START_BIT2 &&
			        data[S_ULTRASONIC_COL] == SENSOR_BIT_USD &&
			        data[S_IR_COL] == SENSOR_BIT_IR &&
			        data[S_HT_COL] == SENSOR_BIT_DHT &&
			        data[S_LIGHT_COL] == SENSOR_BIT_PTR &&
			        data[S_GAS_COL] == SENSOR_BIT_GAS &&
			        data[S_END_COL1] == END_BIT1 &&
			        data[S_END_COL2] == END_BIT2)
			    {
			        sensor->ultrasonic = data[S_ULTRASONIC_COL + 1] * 100 + data[S_ULTRASONIC_COL + 2];
			        sensor->ir = data[S_IR_COL + 1];
			        sensor->humidity = data[S_HT_COL + 1];
			        sensor->temperature = data[S_HT_COL + 2];
			        sensor->heatindex = data[S_HT_COL + 3] + data[S_HT_COL + 4] * 0.01;
			        sensor->light = data[S_LIGHT_COL + 1] * 100 + data[S_LIGHT_COL + 2];
			        sensor->gas = data[S_GAS_COL + 1] * 100 + data[S_GAS_COL + 2];
			
			        showData(SENSOR_PACKET_SIZE, data);
			        printf('  Ultrasonic\t: %03d\t\t IR\t\t: %d\n', sensor->ultrasonic, sensor->ir);
			        printf('  Humidity\t: %02d\t\t Temperature\t: %02d\n', sensor->humidity, sensor->temperature);
			        printf('  Heatindex\t: %02.2f\t\t Light\t\t: %03d\n', sensor->heatindex, sensor->light);
			        printf('  Gas\t\t: %04d\n', sensor->gas);
			
			        // printf('\033[7A');
			    }
			}
			
			void strncat_s(unsigned char *data, unsigned char *buff, int data_size, int buff_size)
			{
			    int i, j;
			
			    for (i = 0, j = data_size; j < SERIAL_MAX_BUFF && i < buff_size; i++, j++)
			    {
			        data[j] = buff[i];
			        // printf('%02X ', buff[i]);
			    }
			}
			
			void showData(int size, unsigned char data[SERIAL_MAX_BUFF])
			{
			    printf('  ');
			    for (int i = 0; i < size; i++)
			    {
			        printf('%02X ', data[i]);
			    }
			    printfln();
			}
			
			void TEST_setSensorStruct(Sensor *sensor)
			{
			    printf('> Set sensor data from packet.\n');
			
			    sensor->ultrasonic = rand() % 100 + 1;
			    sensor->ir = rand() % 1 + 1;
			    sensor->humidity = rand() % 70 + 30;
			    sensor->temperature = rand() % 20 + 20;
			    sensor->heatindex = rand() % 20 + 20 + (rand() % 100) * 0.01;
			    sensor->light = rand() % 800 + 100;
			    sensor->gas = rand() % 700 + 300;
			
			    printf('  Ultrasonic\t: %03d\t\t IR\t\t: %d\n', sensor->ultrasonic, sensor->ir);
			    printf('  Humidity\t: %02d\t\t Temperature\t: %02d\n', sensor->humidity, sensor->temperature);
			    printf('  Heatindex\t: %02.2f\t\t Light\t\t: %03d\n', sensor->heatindex, sensor->light);
			    printf('  Gas\t\t: %04d\n', sensor->gas);
			}
			
			void delay(float time)
			{
			    struct timespec req = {0};
			    double s;
			    double ms;
			    ms = modf(time, &s) * 1000000000;
			
			    req.tv_sec = s;
			    req.tv_nsec = ms;
			    while (nanosleep(&req, NULL) && errno == EINTR);
			}
			
			void printfln()
			{
			    printf('\n');
			}";
	$a_code_front = $a_header+$a_main+$a_recv_start;	//full = 수정부분 + a_end
	$s_code_front = $s_header+$s_main+$s_send_front;	//full = 수정부분 + s_end
	$s_code_full = $s_code_front + $s_send_mod + $s_end;		//piSensor.c 코드
	
	//sensor
	if($sensor === null) $firephp->info('no sensor condition');
	foreach($sensor as $items){
		foreach($items as $s_name=>$s_val){
			$firephp->info($s_name,'sensor name');
			//$firephp->info($s_val,'sensor value');
			if($s_name != 'S_IR'){	//장애물 센서 빼고 range 값 분할
				$range = explode(',', $s_val);
				$r1 = $range[0];
				$r2 = $range[1];
				$firephp->info($r1,'sensor value 1');
				$firephp->info($r2,'sensor value 2');
			}
			
			switch($s_name){
				case 'S_Ultrasonic': break;
				case 'S_Humidity': break;
				case 'S_Temperature':
				case 'S_Heatindex': break; //temperature과 같음
				case 'S_Light': break;
				case 'S_Gas': break;
				case 'S_IR': break;
			}
		}
	}	
	
	//actuator
	if($act === null) $firephp->info('no actuator condition');
	foreach($act as $items){
		foreach($items as $a_name=>$a_val){
			$firephp->info($a_name,'actuator name');
			$firephp->info($a_val,'actuator value');
			
			switch($a_name){
				case 'A_Fan': break;
				case 'A_Servo': break;
				case 'A_Buzzer': break;
			}
		}
	}
	
	echo true;
?>