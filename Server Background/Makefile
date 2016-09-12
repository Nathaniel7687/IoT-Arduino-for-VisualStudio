DEL=rm
CC=gcc
#CC=arm-linux-gnueabihf-gcc

# CFLAGS+=-static
CFLAGS+=-pthread
CFLAGS+=-lpthread
CFLAGS+=-g
CFLAGS+=-Wall

TARGET=server

LDLIBS=$(shell mysql_config --libs)
INCDIR=./inc
SRCDIR=./src
OBJDIR=./src/obj
SRCS=$(wildcard $(SRCDIR)/*.c)
OBJS=$(patsubst %.c,$(OBJDIR)/%.o,$(notdir $(SRCS)))

all: $(TARGET)

$(TARGET): $(OBJS)
	$(CC) $(CFLAGS) $^ -o $@ $(LDLIBS)

$(OBJS): $(OBJDIR)/%.o: $(SRCDIR)/%.c
	@mkdir -p $(OBJDIR)
	$(CC) -I $(INCDIR) -c $(CFLAGS) $< -o $@

clean:
	$(DEL) -rf $(OBJDIR)/*.o
	$(DEL) -rf $(TARGET)