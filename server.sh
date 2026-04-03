#!/bin/bash

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

case "$1" in
  start)
    echo -e "${GREEN}🚀 Starting WeShare servers...${NC}"

    # Check if MySQL is running
    if ! pgrep -x "mysqld" > /dev/null; then
        echo -e "${YELLOW}Starting MySQL...${NC}"
        brew services start mysql
        sleep 2
    fi

    # Start API server
    echo -e "${GREEN}Starting API server on port 8000...${NC}"
    php -S localhost:8000 -t php-api > /dev/null 2>&1 &
    API_PID=$!

    # Start Frontend server
    echo -e "${GREEN}Starting Frontend server on port 8080...${NC}"
    php -S localhost:8080 -t public > /dev/null 2>&1 &
    FRONTEND_PID=$!

    # Save PIDs
    echo $API_PID > .api.pid
    echo $FRONTEND_PID > .frontend.pid

    sleep 2

    echo -e "${GREEN}✅ Servers started successfully!${NC}"
    echo -e "${GREEN}📱 Frontend: http://localhost:8080${NC}"
    echo -e "${GREEN}🔌 API: http://localhost:8000/api${NC}"
    echo -e "${YELLOW}📌 Test group code: TEST1234${NC}"
    ;;

  stop)
    echo -e "${RED}🛑 Stopping WeShare servers...${NC}"

    # Stop API server
    if [ -f .api.pid ]; then
        kill $(cat .api.pid) 2>/dev/null
        rm .api.pid
    fi

    # Stop Frontend server
    if [ -f .frontend.pid ]; then
        kill $(cat .frontend.pid) 2>/dev/null
        rm .frontend.pid
    fi

    # Also kill any remaining PHP servers on our ports
    lsof -ti:8000 | xargs kill 2>/dev/null
    lsof -ti:8080 | xargs kill 2>/dev/null

    echo -e "${GREEN}✅ Servers stopped${NC}"
    ;;

  restart)
    $0 stop
    sleep 1
    $0 start
    ;;

  status)
    echo -e "${YELLOW}📊 WeShare Server Status:${NC}"

    # Check MySQL
    if pgrep -x "mysqld" > /dev/null; then
        echo -e "${GREEN}✅ MySQL is running${NC}"
    else
        echo -e "${RED}❌ MySQL is not running${NC}"
    fi

    # Check API server
    if lsof -Pi :8000 -sTCP:LISTEN -t >/dev/null ; then
        echo -e "${GREEN}✅ API server is running on port 8000${NC}"
    else
        echo -e "${RED}❌ API server is not running${NC}"
    fi

    # Check Frontend server
    if lsof -Pi :8080 -sTCP:LISTEN -t >/dev/null ; then
        echo -e "${GREEN}✅ Frontend server is running on port 8080${NC}"
    else
        echo -e "${RED}❌ Frontend server is not running${NC}"
    fi
    ;;

  *)
    echo "Usage: $0 {start|stop|restart|status}"
    echo "  start   - Start all servers"
    echo "  stop    - Stop all servers"
    echo "  restart - Restart all servers"
    echo "  status  - Check server status"
    exit 1
    ;;
esac